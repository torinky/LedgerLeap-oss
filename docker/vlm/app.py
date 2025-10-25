import traceback
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCR, PPStructure
from PIL import Image
import logging
import time
from pathlib import Path
import tempfile
import os
import cv2
import numpy as np
import fitz  # PyMuPDF

# ロギング設定
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="VLM API", version="1.0.0")

table_engine = None

@app.on_event("startup")
async def startup_event():
    global table_engine
    logger.info("Initializing PPStructure model with Japanese OCR engine...")
    try:
        # 1. Initialize a Japanese-specific OCR engine
        japanese_ocr_engine = PaddleOCR(lang='japan', use_gpu=False, show_log=False)
        
        # 2. Initialize PPStructure for layout/table analysis (using 'ch' models)
        #    and inject the Japanese OCR engine for text recognition.
        table_engine = PPStructure(
            lang='ch',  # Use Chinese models for layout and table detection
            ocr_engine=japanese_ocr_engine, # Inject Japanese engine for OCR
            show_log=False, 
            image_orientation=True
        )
        logger.info("PPStructure model with Japanese OCR engine initialized successfully")
    except Exception as e:
        logger.error(f"Failed to initialize PPStructure model: {e}")
        logger.error(traceback.format_exc())
        raise

@app.get("/health")
async def health_check():
    if table_engine is None:
        raise HTTPException(status_code=503, detail="Model not initialized")
    return {"status": "healthy", "model": "PP-Structure"}

@app.post("/extract_structured")
async def extract_structured_data(file: UploadFile = File(...)):
    if table_engine is None:
        raise HTTPException(status_code=503, detail="Model not initialized")

    tmp_path = None
    total_processing_time = 0
    all_tables_html = []
    page_count = 0

    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        logger.info(f"Processing file for structure analysis: {file.filename}")
        file_extension = Path(file.filename).suffix.lower()

        if file_extension == ".pdf":
            doc = fitz.open(tmp_path)
            page_count = doc.page_count
            for i in range(page_count):
                page = doc.load_page(i)
                pix = page.get_pixmap()
                img = Image.frombytes("RGB", [pix.width, pix.height], pix.samples)
                img_np = np.array(img)

                start_time = time.time()
                result = table_engine(img_np)
                total_processing_time += (time.time() - start_time)
                
                for item in result:
                    if item['type'] == 'table':
                        all_tables_html.append(item['res']['html'])
            doc.close()
        else: # Assume image
            page_count = 1
            img = cv2.imread(tmp_path)
            start_time = time.time()
            result = table_engine(img)
            total_processing_time += (time.time() - start_time)
            for item in result:
                if item['type'] == 'table':
                    all_tables_html.append(item['res']['html'])

        logger.info(f"Structure analysis completed in {total_processing_time:.2f}s. Found {len(all_tables_html)} tables across {page_count} pages.")

        return {
            "success": True,
            "processing_time_s": total_processing_time,
            "page_count": page_count,
            "tables_found": len(all_tables_html),
            "tables_html": all_tables_html,
        }

    except Exception as e:
        logger.error(f"Structure extraction failed: {e}")
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))
    
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)

@app.get("/")
async def root():
    return {
        "message": "VLM API Server",
        "endpoints": {
            "health": "/health",
            "extract_structured": "POST /extract_structured"
        }
    }
