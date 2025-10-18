# docker/embedding/app.py
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import os
import logging
from typing import Optional, List

# --- Logging Setup ---
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# --- Global Model Variables ---
# These will hold the loaded model instance and its name after startup.
model = None
loaded_model_name = None

# --- FastAPI App Initialization ---
app = FastAPI(
    title="Sentence Embedding Service",
    description="A service to generate sentence embeddings using SentenceTransformers.",
    version="1.0.0"
)

# --- Pydantic Models ---
class EmbedRequest(BaseModel):
    texts: List[str]
    normalize: bool = True

class EmbedResponse(BaseModel):
    embeddings: List[List[float]]
    dimension: int
    model: str

# --- Startup Event Handler ---
@app.on_event("startup")
async def load_model_on_startup():
    """
    Loads the SentenceTransformer model on application startup.
    """
    global model, loaded_model_name
    model_name_to_load = os.getenv('EMBEDDING_MODEL', 'intfloat/multilingual-e5-base')

    logger.info("--- Application Startup ---")
    logger.info(f"Attempting to load model: '{model_name_to_load}'")

    try:
        model = SentenceTransformer(
            model_name_to_load,
            device='cpu',
            cache_folder='/app/models'
        )
        loaded_model_name = model_name_to_load
        logger.info(f"Successfully loaded model: '{loaded_model_name}'")
        logger.info("--- Application is ready to accept requests ---")
    except Exception as e:
        logger.error(f"Fatal error: Could not load model on startup. Error: {str(e)}", exc_info=True)
        model = None
        loaded_model_name = None

# --- API Endpoints ---
@app.get("/health")
async def health_check():
    if model is not None:
        return {"status": "healthy", "model_is_loaded": True, "model_name": loaded_model_name}
    else:
        return {"status": "unhealthy", "model_is_loaded": False}

@app.post("/embed", response_model=EmbedResponse)
async def embed_texts(request: EmbedRequest):
    if model is None:
        logger.error("Model is not loaded. Cannot process request.")
        raise HTTPException(status_code=503, detail="Model is not available. Check startup logs.")

    try:
        logger.info(f"Processing embedding request for {len(request.texts)} texts.")

        embeddings = model.encode(
            request.texts,
            normalize_embeddings=request.normalize,
            show_progress_bar=False,
            batch_size=1  # ARM64環境での安定性向上のため
        )
        
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