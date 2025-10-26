# docker/paddle/app.py
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCR
import logging
import time
from pathlib import Path
import tempfile
import os
import cv2
import numpy as np
from PIL import Image

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="PaddleOCR API", version="1.0.0")

ocr_engine = None

@app.on_event("startup")
async def startup_event():
    global ocr_engine
    logger.info("Initializing PaddleOCR 3.x model (minimal config)...")
    
    # Set environment variables to prevent SIGSEGV
    import os
    os.environ['OMP_NUM_THREADS'] = '1'
    os.environ['MKL_NUM_THREADS'] = '1'
    
    # Initialize PaddleOCR 3.x with MINIMAL parameters (proven to work)
    # Based on working examples - use only essential parameters
    ocr_engine = PaddleOCR(
        lang='japan'
        # NO device, use_angle_cls, show_log - removed for 3.x compatibility
        # Let PaddleOCR use all defaults for maximum stability
    )
    logger.info("PaddleOCR 3.x model initialized successfully.")

@app.get("/health")
async def health_check():
    if ocr_engine is None:
        raise HTTPException(status_code=503, detail="Model not initialized.")
    return {"status": "healthy", "model": "PaddleOCR"}

@app.post("/extract/structured")
async def extract_structured(file: UploadFile = File(...)):
    """
    Extract text content from image or PDF file using PaddleOCR.
    """
    if ocr_engine is None:
        raise HTTPException(status_code=500, detail="OCR engine is not available.")
    
    tmp_path = None
    try:
        # Save uploaded file to temporary location
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        start_time = time.time()
        logger.info(f"Processing file: {file.filename}")
        
        # Read image
        if Path(tmp_path).suffix.lower() == '.pdf':
            # For PDF, convert first page to image
            import fitz
            doc = fitz.open(tmp_path)
            page = doc[0]
            pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
            img_data = pix.tobytes("png")
            import io
            pil_img = Image.open(io.BytesIO(img_data)).convert('RGB')
            img = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)
            doc.close()
        else:
            img = cv2.imread(tmp_path)
            if img is None:
                pil_img = Image.open(tmp_path).convert('RGB')
                img = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)
        
        # Execute OCR (3.x API - cls parameter removed)
        result = ocr_engine.ocr(img)
        
        # Process results
        text_lines = []
        html_output = "<html><body>\n"
        
        if result and result[0]:
            for line in result[0]:
                if len(line) >= 2:
                    text = line[1][0]
                    text_lines.append(text)
                    html_output += f"<p>{text}</p>\n"
        
        html_output += "</body></html>"
        markdown_text = "\n\n".join(text_lines)
        
        processing_time_s = time.time() - start_time
        logger.info(f"Processing completed in {processing_time_s:.2f}s")
        
        return {
            "success": True,
            "html": html_output,
            "markdown": markdown_text,
            "processing_time_s": processing_time_s,
        }
    except Exception as e:
        logger.error(f"Error during OCR extraction: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)
