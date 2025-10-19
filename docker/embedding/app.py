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

# --- FastAPI App Initialization ---
app = FastAPI(
    title="Sentence Embedding Service",
    description="A service to generate sentence embeddings using SentenceTransformers.",
    version="1.1.0"
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
    """
    global model, loaded_model_name, app_status, startup_error
    
    app_status = AppStatus.LOADING
    
    try:
        model_name_to_load = os.getenv('EMBEDDING_MODEL', 'intfloat/multilingual-e5-base')
        device = os.getenv('RAG_DEVICE', 'cpu')

        logger.info("--- Background Model Loading Started ---")
        logger.info(f"Attempting to load model: '{model_name_to_load}' on device: {device}")
        
        # --- Performance Settings Log ---
        logger.info("Performance settings:")
        logger.info(f"  - Device: {device}")
        logger.info(f"  - PyTorch num_threads: {torch.get_num_threads()}")
        logger.info(f"  - PyTorch num_interop_threads: {torch.get_num_interop_threads()}")
        logger.info(f"  - Batch size: {os.getenv('EMBEDDING_BATCH_SIZE', '1')}")
        logger.info(f"  - Convert to numpy: {os.getenv('RAG_CONVERT_TO_NUMPY', 'true').lower() == 'true'}")
        # --- End of Performance Settings Log ---

        model = SentenceTransformer(
            model_name_to_load,
            device=device,
            cache_folder='/app/models'
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