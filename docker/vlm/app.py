import traceback
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCR
import logging
import time
from pathlib import Path
import tempfile
import os

# ロギング設定
logging.basicConfig(level=logging.DEBUG)
logger = logging.getLogger(__name__)

app = FastAPI(title="VLM API", version="1.0.0")

# (中略)

@app.on_event("startup")
async def startup_event():
    global ocr
    logger.info("Initializing PaddleOCR model...")
    try:
        ocr = PaddleOCR(
            use_angle_cls=True,
            lang='japan',  # 日本語モデル
            use_gpu=False,  # CPU使用
            show_log=False
        )
        logger.info("PaddleOCR model initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize PaddleOCR: {e}")
        raise

@app.get("/health")
async def health_check():
    """ヘルスチェックエンドポイント"""
    if ocr is None:
        raise HTTPException(status_code=503, detail="Model not initialized")
    return {
        "status": "healthy",
        "model": "PaddleOCR",
        "version": "2.7.0"
    }

@app.post("/extract")
async def extract_text(file: UploadFile = File(...)):
    """
    画像/PDFからテキストを抽出
    
    Returns:
        {
            "success": bool,
            "text": str,
            "confidence": float,
            "processing_time_ms": int
        }
    """
    if ocr is None:
        logger.error("OCR model is not initialized.")
        raise HTTPException(status_code=503, detail="Model not initialized")
    
    tmp_path = None  # 初期化
    try:
        # ファイルを一時的に保存
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name
        
        logger.info(f"File saved to temporary path: {tmp_path}")
        logger.info(f"Processing file: {file.filename} ({len(content)} bytes)")
        
        # OCR実行
        logger.info("Starting OCR process...")
        start_time = time.time()
        result = ocr.ocr(tmp_path, cls=True)
        processing_time_ms = int((time.time() - start_time) * 1000)
        logger.info(f"OCR process finished in {processing_time_ms}ms.")
        logger.debug(f"Raw OCR result: {result}")

        # 結果解析
        if result is None or result[0] is None or len(result[0]) == 0:
            logger.warning("No text detected in the document.")
            return {
                "success": False,
                "text": "",
                "confidence": 0.0,
                "processing_time_ms": processing_time_ms,
                "error": "No text detected"
            }
        
        # テキストと信頼度を抽出
        text_lines = []
        confidences = []
        
        for line in result[0]:
            if line:
                text = line[1][0]
                confidence = line[1][1]
                text_lines.append(text)
                confidences.append(confidence)
        
        full_text = "\n".join(text_lines)
        avg_confidence = sum(confidences) / len(confidences) if confidences else 0.0
        
        logger.info(f"Extraction completed: {len(text_lines)} lines, confidence={avg_confidence:.3f}")
        
        return {
            "success": True,
            "text": full_text,
            "confidence": avg_confidence,
            "processing_time_ms": processing_time_ms,
            "line_count": len(text_lines)
        }
        
    except Exception as e:
        logger.error(f"Extraction failed: {e}")
        logger.error(traceback.format_exc()) # スタックトレースをログに出力
        raise HTTPException(status_code=500, detail=str(e))
    
    finally:
        # 一時ファイル削除
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)
            logger.info(f"Temporary file deleted: {tmp_path}")


# (以下略)


@app.get("/")
async def root():
    return {
        "message": "VLM API Server",
        "endpoints": {
            "health": "/health",
            "extract": "POST /extract"
        }
    }
