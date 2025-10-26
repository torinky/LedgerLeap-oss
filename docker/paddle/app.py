# docker/paddle/app.py
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCR
# This import path should work with paddleocr==2.7.3
import numpy as np
from PIL import Image
import logging
import time
from pathlib import Path
import tempfile
import os
import cv2

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="PaddleOCR API", version="2.7.3")

structure_engine = None

@app.on_event("startup")
async def startup_event():
    global structure_engine
    logger.info("Initializing PaddleOCR model for PP-Structure...")
    # Initialize PaddleOCR with structure recognition enabled
    structure_engine = PaddleOCR(use_angle_cls=True, lang='japan', structure_version='PP-StructureV2', layout=True, show_log=True, use_gpu=False)
    logger.info("PaddleOCR PP-Structure model initialized successfully.")

@app.get("/health")
async def health_check():
    if structure_engine is None:
        raise HTTPException(status_code=503, detail="Model not initialized.")
    return {"status": "healthy", "model": "PaddleOCR PP-Structure"}

@app.post("/extract/structured")
async def extract_structured(file: UploadFile = File(...)):
    if structure_engine is None:
        raise HTTPException(status_code=500, detail="Structure model is not available.")
    
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        start_time = time.time()
        
        img = cv2.imread(tmp_path)
        if img is None:
            # Try to open with Pillow as a fallback for formats cv2 might not handle
            try:
                pil_img = Image.open(tmp_path).convert('RGB')
                # Convert PIL image to OpenCV format
                img = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)
            except Exception as pil_e:
                raise HTTPException(status_code=400, detail=f"Invalid image file: {pil_e}")

        # Run structure analysis
        result = structure_engine.ocr(img, cls=False)
        
        # The result for structure analysis is a list of dictionaries
        # The sorting function is not available in this version, so we use the raw result.
        res = result
        
        html_output = "<html><body>"
        for region in res:
            if region['type'] == 'table':
                # The html content is already prepared in the result
                html_output += region['res']['html']
            else:
                for line in region['res']:
                    html_output += f"<p>{line[1][0]}</p>"
        html_output += "</body></html>"

        processing_time_s = time.time() - start_time
        
        return {
            "success": True,
            "html": html_output,
            "raw_result": res, # For debugging
            "processing_time_s": processing_time_s,
        }
    except Exception as e:
        logger.error(f"Error during structured extraction: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)