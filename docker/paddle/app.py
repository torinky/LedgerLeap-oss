# docker/paddle/app.py
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCR
import logging
import time
from pathlib import Path
import tempfile
import os

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="PaddleOCR API", version="1.0.0")

ocr_engine = None

@app.on_event("startup")
async def startup_event():
    global ocr_engine
    logger.info("Initializing PaddleOCR model...")
    ocr_engine = PaddleOCR(use_angle_cls=True, lang='japan', use_gpu=False, show_log=False)
    logger.info("PaddleOCR model initialized successfully.")

@app.get("/health")
async def health_check():
    if ocr_engine is None:
        raise HTTPException(status_code=503, detail="Model not initialized.")
    return {"status": "healthy", "model": "PaddleOCR"}

@app.post("/extract/text")
async def extract_text(file: UploadFile = File(...)):
    if ocr_engine is None:
        raise HTTPException(status_code=500, detail="Model is not available.")
    
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        start_time = time.time()
        result = ocr_engine.ocr(tmp_path, cls=True)
        processing_time_s = time.time() - start_time

        if result is None or result[0] is None:
            return {"success": False, "text": "", "confidence": 0.0, "processing_time_s": processing_time_s}

        text_lines = [line[1][0] for line in result[0] if line]
        confidences = [line[1][1] for line in result[0] if line]
        
        full_text = "\n".join(text_lines)
        avg_confidence = sum(confidences) / len(confidences) if confidences else 0.0
        
        return {
            "success": True,
            "text": full_text,
            "confidence": avg_confidence,
            "processing_time_s": processing_time_s,
        }
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)

# --- 以下、将来の構造化テキスト抽出機能のための準備 ---
# from paddleocr import PPStructure
# import cv2
# import numpy as np
# import fitz # PyMuPDF

# structure_engine = None

# @app.on_event("startup")
# async def startup_event_structured():
#     # logger.info("Initializing PPStructure model...")
#     # try:
#     #     # 将来、PP-Structureが日本語のレイアウト解析に正式対応した場合、
#     #     # 以下のように lang='japan' だけで初期化できると期待される。
#     #     structure_engine = PPStructure(lang='japan', show_log=False, image_orientation=True)
#     #     logger.info("PPStructure model initialized successfully.")
#     # except Exception as e:
#     #     logger.error(f"Failed to initialize PPStructure model: {e}")
#     pass

# @app.post("/extract/structured")
# async def extract_structured(file: UploadFile = File(...)):
#     # if structure_engine is None:
#     #     raise HTTPException(status_code=500, detail="Structure model is not available.")
#     
#     # tmp_path = None
#     # try:
#     #     with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
#     #         content = await file.read()
#     #         tmp.write(content)
#     #         tmp_path = tmp.name
#     # 
#     #     start_time = time.time()
#     #     
#     #     img = cv2.imread(tmp_path)
#     #     result = structure_engine(img)
#     #     
#     #     processing_time_s = time.time() - start_time
#     #     
#     #     # TODO: resultのパース処理
#     #     
#     #     return {
#     #         "success": True,
#     #         "result": result, # 仮
#     #         "processing_time_s": processing_time_s,
#     #     }
#     # finally:
#     #     if tmp_path and os.path.exists(tmp_path):
#     #         os.unlink(tmp_path)
#     raise HTTPException(status_code=501, detail="Structured extraction for Japanese is not yet implemented.")
