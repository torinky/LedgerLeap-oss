# docker/embedding/app.py
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import os
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

# モデルのロード（起動時）
MODEL_NAME = os.getenv('EMBEDDING_MODEL', 'BAAI/bge-m3')
USE_ONNX = os.getenv('USE_ONNX', 'true').lower() == 'true'

logger.info(f"Loading model: {MODEL_NAME} (ONNX: {USE_ONNX})")

model = SentenceTransformer(
    MODEL_NAME,
    device='cpu',
    cache_folder='/app/models'
)

if USE_ONNX:
    # ONNX最適化の適用
    logger.info("Applying ONNX optimization...")
    # 実装詳細はPoC中に検証

class EmbedRequest(BaseModel):
    texts: list[str]
    normalize: bool = True

class EmbedResponse(BaseModel):
    embeddings: list[list[float]]
    dimension: int
    model: str

@app.get("/health")
async def health_check():
    return {"status": "healthy", "model": MODEL_NAME}

@app.post("/embed", response_model=EmbedResponse)
async def embed_texts(request: EmbedRequest):
    try:
        logger.info(f"Embedding {len(request.texts)} texts...")
        
        embeddings = model.encode(
            request.texts,
            normalize_embeddings=request.normalize,
            show_progress_bar=False
        )
        
        return EmbedResponse(
            embeddings=embeddings.tolist(),
            dimension=embeddings.shape[1],
            model=MODEL_NAME
        )
    except Exception as e:
        logger.error(f"Embedding failed: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))
