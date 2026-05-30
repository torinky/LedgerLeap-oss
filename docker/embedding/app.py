# docker/embedding/app.py
import asyncio
from enum import Enum
from fastapi import FastAPI, HTTPException, Response
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import os
import logging
import torch
from pathlib import Path
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
startup_task: Optional[asyncio.Task] = None

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
_REQUIRED_SNAPSHOT_FILES = (
    "config.json",
    "modules.json",
    "sentence_bert_config.json",
    "1_Pooling/config.json",
)
_REQUIRED_WEIGHT_FILES = (
    "model.safetensors",
    "pytorch_model.bin",
)
_REQUIRED_TOKENIZER_FILES = (
    "tokenizer.json",
    "tokenizer.model",
)


def _snapshot_cache_state(snapshot_path: Path) -> tuple[bool, list[str]]:
    """Return whether a SentenceTransformer snapshot is complete enough to load locally."""
    missing_files = [
        relative_path
        for relative_path in _REQUIRED_SNAPSHOT_FILES
        if not (snapshot_path / relative_path).is_file()
    ]

    if not any((snapshot_path / relative_path).is_file() for relative_path in _REQUIRED_WEIGHT_FILES):
        missing_files.append("model.safetensors|pytorch_model.bin")

    if not any((snapshot_path / relative_path).is_file() for relative_path in _REQUIRED_TOKENIZER_FILES):
        missing_files.append("tokenizer.json|tokenizer.model")

    return len(missing_files) == 0, missing_files


def _locate_cached_snapshot(model_name: str, cache_folder: str) -> Optional[Path]:
    """Return the local snapshot path when HuggingFace cache contains the target model."""
    try:
        from huggingface_hub import try_to_load_from_cache

        result = try_to_load_from_cache(
            repo_id=model_name,
            filename="config.json",
            cache_dir=cache_folder,
        )
        if not isinstance(result, str):
            logger.info(f"No cache found for model: '{model_name}'")
            return None

        snapshot_path = Path(result).parent
        logger.info(f"Cache detected: {snapshot_path}")
        return snapshot_path
    except Exception as e:
        logger.warning(f"Cache check failed (assuming no cache): {e}")
        return None


def _resolve_local_files_only(model_name: str, cache_folder: str) -> bool:
    """Return whether the model should be loaded without network access."""
    offline_env = os.getenv('EMBEDDING_OFFLINE', 'auto').strip()
    cached_snapshot = _locate_cached_snapshot(model_name, cache_folder)
    has_complete_cache = False
    missing_files: list[str] = []

    if cached_snapshot is not None:
        has_complete_cache, missing_files = _snapshot_cache_state(cached_snapshot)
        if not has_complete_cache:
            logger.warning(
                "Cache snapshot detected but incomplete for model '%s' at %s. Missing: %s",
                model_name,
                cached_snapshot,
                ", ".join(missing_files),
            )

    if offline_env == '1':
        if not has_complete_cache or cached_snapshot is None:
            missing_summary = ", ".join(missing_files) if missing_files else "snapshot not found"
            raise RuntimeError(
                f"EMBEDDING_OFFLINE=1 requires a complete local SentenceTransformer snapshot for "
                f"'{model_name}' in '{cache_folder}', but it was incomplete ({missing_summary})."
            )

        logger.info("Offline mode: FORCED ON (EMBEDDING_OFFLINE=1)")
        return True

    if offline_env == '0':
        logger.info("Offline mode: FORCED OFF (EMBEDDING_OFFLINE=0) → will access HuggingFace Hub")
        return False

    if has_complete_cache and cached_snapshot is not None:
        logger.info("Offline mode: AUTO → complete cache found, starting in offline mode (no network needed)")
        return True

    logger.info("Offline mode: AUTO → no complete cache found, downloading from HuggingFace Hub (internet required)")
    return False


def _is_model_cached(model_name: str, cache_folder: str) -> bool:
    """
    HuggingFace キャッシュに、ローカル起動に必要な SentenceTransformer
    スナップショットが存在するか確認する。

    Returns:
        True  → 完全なスナップショットが存在する → オフラインで起動可能
        False → 不完全または未検出 → HuggingFace Hub からダウンロードが必要
    """
    cached_snapshot = _locate_cached_snapshot(model_name, cache_folder)
    if cached_snapshot is None:
        return False

    is_complete, _ = _snapshot_cache_state(cached_snapshot)
    return is_complete

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
def _load_model_sync():
    """
    The actual model loading logic, designed to run off the event loop.
    Updates the global application state based on the outcome.

    オフラインモードの挙動:
    - キャッシュが存在する場合: HuggingFace Hub に一切接続せずローカルから起動
    - キャッシュが存在しない場合: HuggingFace Hub からダウンロードしてキャッシュを作成
    - 次回起動以降は自動的にオフラインモードで動作
    """
    global model, loaded_model_name, app_status, startup_error
    
    app_status = AppStatus.LOADING
    cache_folder = os.getenv('EMBEDDING_CACHE_DIR', '/app/models')

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


async def _load_model():
    """Run the blocking model load in a worker thread so startup stays responsive."""
    await asyncio.to_thread(_load_model_sync)

# --- Startup Event Handler ---
@app.on_event("startup")
async def startup_event():
    """
    Triggers the model loading in a background task on application startup.
    """
    global app_status, startup_task
    app_status = AppStatus.STARTING
    
    configure_performance()
    
    logger.info("--- Application Startup Event: Triggering model load ---")
    startup_task = asyncio.create_task(_load_model())

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

@app.post(
    "/embed",
    response_model=EmbedResponse,
    responses={500: {"description": "An error occurred during embedding."}},
)
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