# docker/embedding/app.py
import asyncio
from enum import Enum
from fastapi import FastAPI, HTTPException, Response
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import os
import logging
import torch
from typing import Optional, List
from starlette.status import HTTP_503_SERVICE_UNAVAILABLE

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# --- Application State ---
class AppStatus(str, Enum):
    STARTING = "starting"
    LOADING = "loading"
    READY = "ready"
    ERROR = "error"

# --- Global State Variables ---
app_status: AppStatus = AppStatus.STARTING
model: Optional[SentenceTransformer] = None
loaded_model_name: Optional[str] = None
startup_error: Optional[str] = None

# --- Performance Configuration from Environment Variables ---
def configure_performance():
    """Configure PyTorch performance settings from environment variables"""
    num_threads = int(os.getenv('RAG_NUM_THREADS', '0'))
    num_interop_threads = int(os.getenv('RAG_NUM_INTEROP_THREADS', '0'))
    
    if num_threads > 0:
        torch.set_num_threads(num_threads)
        logger.info(f"Set PyTorch num_threads to: {num_threads}")
    
    if num_interop_threads > 0:
        torch.set_num_interop_threads(num_interop_threads)
        logger.info(f"Set PyTorch num_interop_threads to: {num_interop_threads}")

# --- Cache Detection ---
def _is_model_cached(model_name: str, cache_folder: str) -> bool:
    """
    HuggingFace キャッシュにモデルが存在するか確認する。
    huggingface_hub の try_to_load_from_cache を使って config.json の
    存在をチェックする（軽量なファイルで判定）。
    
    Returns:
        True  → キャッシュ存在 → オフラインで起動可能
        False → キャッシュなし → HuggingFace Hub からダウンロードが必要
    """
    try:
        from huggingface_hub import try_to_load_from_cache
        result = try_to_load_from_cache(
            repo_id=model_name,
            filename="config.json",
            cache_dir=cache_folder,
        )
        # result が文字列（ファイルパス）の場合はキャッシュが存在する
        is_cached = isinstance(result, str)
        if is_cached:
            logger.info(f"Cache detected: {result}")
        else:
            logger.info(f"No cache found for model: '{model_name}'")
        return is_cached
    except Exception as e:
        logger.warning(f"Cache check failed (assuming no cache): {e}")
        return False

def _resolve_local_files_only(model_name: str, cache_folder: str) -> bool:
    """
    EMBEDDING_OFFLINE 環境変数とキャッシュ状態から local_files_only を決定する。

    EMBEDDING_OFFLINE=1   → 強制オフライン（キャッシュがなければ起動失敗）
    EMBEDDING_OFFLINE=0   → 強制オンライン（常に HF Hub に接続して確認）
    未設定 or その他      → 自動検出: キャッシュあり→オフライン / なし→オンライン
    """
    offline_env = os.getenv('EMBEDDING_OFFLINE', 'auto').strip()

    if offline_env == '1':
        logger.info("Offline mode: FORCED ON (EMBEDDING_OFFLINE=1)")
        return True

    if offline_env == '0':
        logger.info("Offline mode: FORCED OFF (EMBEDDING_OFFLINE=0) → will access HuggingFace Hub")
        return False

    # auto: キャッシュ存在チェックで自動判定
    is_cached = _is_model_cached(model_name, cache_folder)
    if is_cached:
        logger.info("Offline mode: AUTO → cache found, starting in offline mode (no network needed)")
    else:
        logger.info("Offline mode: AUTO → no cache found, downloading from HuggingFace Hub (internet required)")
    return is_cached

# --- FastAPI App Initialization ---
app = FastAPI(
    title="Sentence Embedding Service",
    description="A service to generate sentence embeddings using SentenceTransformers.",
    version="1.2.0"
)

# --- Pydantic Models ---
class EmbedRequest(BaseModel):
    texts: List[str]
    normalize: bool = True

class EmbedResponse(BaseModel):
    embeddings: List[List[float]]
    dimension: int
    model: str

# --- Model Loading Logic ---
async def _load_model():
    """
    The actual model loading logic, designed to be run in the background.
    Updates the global application state based on the outcome.

    オフラインモードの挙動:
    - キャッシュが存在する場合: HuggingFace Hub に一切接続せずローカルから起動
    - キャッシュが存在しない場合: HuggingFace Hub からダウンロードしてキャッシュを作成
    - 次回起動以降は自動的にオフラインモードで動作
    """
    global model, loaded_model_name, app_status, startup_error
    
    app_status = AppStatus.LOADING
    cache_folder = '/app/models'
    
    try:
        model_name_to_load = os.getenv('EMBEDDING_MODEL', 'intfloat/multilingual-e5-base')
        device = os.getenv('RAG_DEVICE', 'cpu')

        logger.info("--- Background Model Loading Started ---")
        logger.info(f"Attempting to load model: '{model_name_to_load}' on device: {device}")

        # キャッシュ自動検出または環境変数による明示的な制御
        local_files_only = _resolve_local_files_only(model_name_to_load, cache_folder)
        
        # --- Performance Settings Log ---
        logger.info("Performance settings:")
        logger.info(f"  - Device: {device}")
        logger.info(f"  - local_files_only: {local_files_only}")
        logger.info(f"  - PyTorch num_threads: {torch.get_num_threads()}")
        logger.info(f"  - PyTorch num_interop_threads: {torch.get_num_interop_threads()}")
        logger.info(f"  - Batch size: {os.getenv('EMBEDDING_BATCH_SIZE', '1')}")
        logger.info(f"  - Convert to numpy: {os.getenv('RAG_CONVERT_TO_NUMPY', 'true').lower() == 'true'}")
        # --- End of Performance Settings Log ---

        model = SentenceTransformer(
            model_name_to_load,
            device=device,
            cache_folder=cache_folder,
            local_files_only=local_files_only,
        )
        loaded_model_name = model_name_to_load
        
        app_status = AppStatus.READY
        logger.info(f"Successfully loaded model: '{loaded_model_name}'")
        logger.info("--- Application is ready to accept requests ---")

    except Exception as e:
        app_status = AppStatus.ERROR
        startup_error = str(e)
        logger.error(f"Fatal error: Could not load model. Error: {startup_error}", exc_info=True)
        model = None
        loaded_model_name = None

# --- Startup Event Handler ---
@app.on_event("startup")
async def startup_event():
    """
    Triggers the model loading in a background task on application startup.
    """
    global app_status
    app_status = AppStatus.STARTING
    
    configure_performance()
    
    logger.info("--- Application Startup Event: Triggering model load ---")
    asyncio.create_task(_load_model())

# --- API Endpoints ---
@app.get("/health")
async def health_check(response: Response):
    """
    Provides the current status of the application.
    - 200 OK: If the model is loaded and ready.
    - 503 Service Unavailable: If the model is loading, has failed to load, or the app is starting.
    """
    if app_status == AppStatus.READY:
        return {"status": "healthy", "model_is_loaded": True, "model_name": loaded_model_name}
    
    if app_status == AppStatus.LOADING:
        response.status_code = HTTP_503_SERVICE_UNAVAILABLE
        response.headers["Retry-After"] = "30"
        return {"status": "loading", "model_is_loaded": False, "message": "Model is currently loading. Please try again in 30 seconds."}
        
    # For STARTING or ERROR states
    response.status_code = HTTP_503_SERVICE_UNAVAILABLE
    return {"status": "unhealthy", "model_is_loaded": False, "error": startup_error, "message": "Model is not available."}

@app.post("/embed", response_model=EmbedResponse)
async def embed_texts(request: EmbedRequest):
    """
    Generates embeddings for a list of texts.
    Returns a 503 error if the model is not ready.
    """
    if app_status != AppStatus.READY:
        raise HTTPException(
            status_code=HTTP_503_SERVICE_UNAVAILABLE,
            detail=f"Model is not ready. Current status: {app_status}. Please wait and try again.",
            headers={"Retry-After": "30"}
        )

    try:
        # --- Detailed Logging ---
        total_chars = sum(len(text) for text in request.texts)
        avg_chars = total_chars / len(request.texts) if request.texts else 0
        logger.info(f"Processing embedding request for {len(request.texts)} texts "
                    f"({total_chars} total chars, {avg_chars:.2f} avg chars).")
        
        start_time = asyncio.get_event_loop().time()
        # --- End of Detailed Logging ---

        batch_size = int(os.getenv('EMBEDDING_BATCH_SIZE', '1'))
        convert_to_numpy = os.getenv('RAG_CONVERT_TO_NUMPY', 'true').lower() == 'true'
        
        embeddings = model.encode(
            request.texts,
            normalize_embeddings=request.normalize,
            show_progress_bar=False,
            batch_size=batch_size,
            convert_to_numpy=convert_to_numpy
        )
        
        # --- Performance Logging ---
        end_time = asyncio.get_event_loop().time()
        duration_ms = (end_time - start_time) * 1000
        logger.info(f"Embedding completed in {duration_ms:.2f} ms.")
        # --- End of Performance Logging ---
        
        return EmbedResponse(
            embeddings=embeddings.tolist(),
            dimension=model.get_sentence_embedding_dimension(),
            model=loaded_model_name
        )
    except Exception as e:
        logger.error(f"Embedding failed during processing: {str(e)}", exc_info=True)
        raise HTTPException(status_code=500, detail=f"An error occurred during embedding: {str(e)}")

@app.get("/")
async def root():
    return {"message": "Sentence Embedding Service is running. Use the /embed endpoint to generate embeddings."}