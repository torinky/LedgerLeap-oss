"""
Unified VLM API Wrapper
Provides consistent API interface regardless of underlying VLM model (PaddleOCR, PaddleOCR-VL, etc.)
"""
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
import logging
import time
from pathlib import Path
import tempfile
import os
import glob
import shutil
import subprocess
from typing import Optional, Dict, Any

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(
    title="Unified VLM API",
    version="1.0.0",
    description="Unified interface for Vision-Language Models (PaddleOCR, PaddleOCR-VL, etc.)"
)

# Global variables for model backend
model_engine = None
model_type = None
initialization_error = None

def initialize_paddleocr():
    """Initialize PaddleOCR (OCR-only) backend"""
    from paddleocr import PaddleOCR
    logger.info("Initializing PaddleOCR backend...")
    
    device = os.environ.get("PADDLEOCR_DEVICE", "cpu")
    use_gpu = device.lower() == "gpu"
    
    engine = PaddleOCR(
        use_angle_cls=True,
        lang='japan',
        use_gpu=use_gpu
    )
    logger.info(f"PaddleOCR initialized successfully (device: {device})")
    return engine, "paddleocr"

def initialize_paddleocr_vl():
    """Initialize PaddleOCR-VL (advanced document understanding) backend"""
    from paddleocr import PaddleOCRVL
    logger.info("Initializing PaddleOCR-VL backend...")
    
    device = os.environ.get("PADDLEOCR_DEVICE", "gpu")
    
    engine = PaddleOCRVL(
        device=device,
        use_doc_orientation_classify=True,
        use_layout_detection=True,
        use_doc_unwarping=True,
        use_chart_recognition=True,
        format_block_content=True
    )
    logger.info(f"PaddleOCR-VL initialized successfully (device: {device})")
    return engine, "paddleocr-vl"

def initialize_marker():
    """Initialize Marker (PDF to Markdown) backend"""
    import subprocess
    logger.info("Initializing Marker backend...")
    
    # Check if marker_single command is available
    try:
        subprocess.run(["marker_single", "--help"], check=True, capture_output=True, timeout=10)
        logger.info("Marker CLI initialized successfully")
        return None, "marker"  # Marker uses CLI, no Python object needed
    except (subprocess.CalledProcessError, FileNotFoundError, subprocess.TimeoutExpired) as e:
        raise RuntimeError(f"Marker CLI not available: {e}")

def initialize_mineru():
    """Initialize MinerU (PDF to Markdown) backend"""
    import subprocess
    logger.info("Initializing MinerU backend...")
    
    # Force CPU mode
    os.environ['CUDA_VISIBLE_DEVICES'] = ''
    os.environ['DEVICE_MODE'] = 'cpu'
    
    # Check if mineru command is available
    try:
        subprocess.run(["mineru", "--help"], check=True, capture_output=True, timeout=10)
        logger.info("MinerU CLI initialized successfully")
        return None, "mineru"  # MinerU uses CLI, no Python object needed
    except (subprocess.CalledProcessError, FileNotFoundError, subprocess.TimeoutExpired) as e:
        raise RuntimeError(f"MinerU CLI not available: {e}")

@app.on_event("startup")
async def startup_event():
    """Initialize the appropriate VLM backend based on environment variable"""
    global model_engine, model_type, initialization_error
    
    vlm_model = os.environ.get("VLM_MODEL", "paddleocr").lower()
    logger.info("=" * 80)
    logger.info(f"Initializing VLM backend: {vlm_model}")
    logger.info("=" * 80)
    
    try:
        if vlm_model == "paddleocr-vl":
            model_engine, model_type = initialize_paddleocr_vl()
        elif vlm_model == "marker":
            model_engine, model_type = initialize_marker()
        elif vlm_model == "mineru":
            model_engine, model_type = initialize_mineru()
        else:  # Default to paddleocr
            model_engine, model_type = initialize_paddleocr()
        
        logger.info("=" * 80)
        logger.info(f"✅ SUCCESS! Model type: {model_type}")
        logger.info("=" * 80)
        
    except Exception as e:
        error_msg = str(e)
        initialization_error = error_msg
        logger.error("=" * 80)
        logger.error(f"❌ FAILED to initialize VLM backend")
        logger.error(f"Error: {error_msg}")
        logger.error("=" * 80)
        model_engine = None
        model_type = None

@app.get("/health")
async def health_check():
    """Health check endpoint - consistent across all VLM backends"""
    # Check if initialization was successful (model_type is set)
    if model_type is None:
        raise HTTPException(
            status_code=503,
            detail={
                "status": "unhealthy",
                "model": "unknown",
                "error": initialization_error or "Model not initialized"
            }
        )
    
    device = os.environ.get("PADDLEOCR_DEVICE", "cpu")
    return {
        "status": "healthy",
        "model": model_type,
        "device": device
    }

def process_with_paddleocr(file_path: str) -> Dict[str, Any]:
    """Process document with PaddleOCR backend"""
    import cv2
    import numpy as np
    from PIL import Image
    
    # Read image
    if Path(file_path).suffix.lower() == '.pdf':
        import fitz
        doc = fitz.open(file_path)
        page = doc[0]
        pix = page.get_pixmap(matrix=fitz.Matrix(2, 2))
        img_data = pix.tobytes("png")
        import io
        pil_img = Image.open(io.BytesIO(img_data)).convert('RGB')
        img = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)
        doc.close()
    else:
        img = cv2.imread(file_path)
        if img is None:
            pil_img = Image.open(file_path).convert('RGB')
            img = cv2.cvtColor(np.array(pil_img), cv2.COLOR_RGB2BGR)
    
    # Execute OCR
    result = model_engine.ocr(img, cls=True)
    
    # Process results
    text_lines = []
    html_parts = ["<html><body>"]
    
    if result and result[0]:
        for line in result[0]:
            if len(line) >= 2:
                text = line[1][0]
                text_lines.append(text)
                html_parts.append(f"<p>{text}</p>")
    
    html_parts.append("</body></html>")
    
    return {
        "html": "\n".join(html_parts),
        "markdown": "\n\n".join(text_lines),
        "structured_data": {
            "pages": [{"page_index": 0, "text_lines": text_lines}],
            "text_blocks": [{"type": "text", "content": line} for line in text_lines],
            "tables": [],
            "key_value_pairs": []
        }
    }

def process_with_marker(file_path: str) -> Dict[str, Any]:
    """Process document with Marker backend (PDF to Markdown)"""
    import subprocess
    import tempfile
    import shutil
    
    tmp_out_dir = tempfile.mkdtemp()
    
    try:
        lowres_dpi = os.environ.get("MARKER_LOWRES_DPI", "96")
        highres_dpi = os.environ.get("MARKER_HIGHRES_DPI", "192")
        
        cmd = [
            "marker_single", file_path, 
            "--output_dir", tmp_out_dir,
            "--lowres_image_dpi", lowres_dpi,
            "--highres_image_dpi", highres_dpi
        ]
        
        subprocess.run(cmd, check=True, capture_output=True, text=True, timeout=600)
        
        # Find output markdown
        from pathlib import Path
        all_items = list(Path(tmp_out_dir).iterdir())
        if all_items and all_items[0].is_dir():
            all_items = list(all_items[0].iterdir())
        
        output_md_files = [f for f in all_items if f.is_file() and f.suffix == '.md']
        if not output_md_files:
            raise ValueError("Marker did not produce markdown output")
        
        with open(output_md_files[0], "r", encoding="utf-8") as f:
            markdown_text = f.read()
        
        return {
            "html": f"<html><body><pre>{markdown_text}</pre></body></html>",
            "markdown": markdown_text,
            "structured_data": {
                "pages": [{"page_index": 0, "content": markdown_text}],
                "text_blocks": [{"type": "markdown", "content": markdown_text}],
                "tables": [],
                "key_value_pairs": []
            }
        }
    finally:
        if os.path.exists(tmp_out_dir):
            shutil.rmtree(tmp_out_dir)

def process_with_mineru(file_path: str) -> Dict[str, Any]:
    """Process document with MinerU backend (PDF to Markdown)"""
    import subprocess
    import tempfile
    import shutil
    import glob
    
    tmp_out_dir = tempfile.mkdtemp()
    
    try:
        cmd = ["mineru", "-p", file_path, "-o", tmp_out_dir]
        
        logger.info(f"Running MinerU command: {' '.join(cmd)}")
        result = subprocess.run(
            cmd, 
            capture_output=True, 
            text=True, 
            timeout=300,
            cwd=tmp_out_dir
        )
        
        logger.info(f"MinerU return code: {result.returncode}")
        logger.info(f"MinerU stdout: {result.stdout[:500]}")
        logger.info(f"MinerU stderr: {result.stderr[:500]}")
        
        if result.returncode != 0:
            logger.error(f"MinerU full stderr: {result.stderr}")
            logger.error(f"MinerU full stdout: {result.stdout}")
            raise RuntimeError(f"MinerU failed: {result.stderr or result.stdout}")
        
        # Find markdown output
        base_name = os.path.splitext(os.path.basename(file_path))[0]
        
        # Debug: List all files in output directory
        logger.info(f"MinerU output directory: {tmp_out_dir}")
        all_files = glob.glob(os.path.join(tmp_out_dir, "**", "*"), recursive=True)
        logger.info(f"MinerU created {len(all_files)} files/dirs:")
        for f in all_files[:20]:  # Show first 20
            logger.info(f"  - {f}")
        
        search_patterns = [
            os.path.join(tmp_out_dir, f"{base_name}.md"),
            os.path.join(tmp_out_dir, "**", "*.md"),
        ]
        
        markdown_content = ""
        for pattern in search_patterns:
            md_files = glob.glob(pattern, recursive=True)
            logger.info(f"Pattern '{pattern}' found {len(md_files)} files")
            if md_files:
                logger.info(f"Using markdown file: {md_files[0]}")
                with open(md_files[0], "r", encoding="utf-8") as f:
                    markdown_content = f.read()
                break
        
        if not markdown_content:
            raise ValueError(f"MinerU did not produce markdown output. Checked patterns: {search_patterns}")
        
        return {
            "html": f"<html><body><pre>{markdown_content}</pre></body></html>",
            "markdown": markdown_content,
            "structured_data": {
                "pages": [{"page_index": 0, "content": markdown_content}],
                "text_blocks": [{"type": "markdown", "content": markdown_content}],
                "tables": [],
                "key_value_pairs": []
            }
        }
    finally:
        if os.path.exists(tmp_out_dir):
            shutil.rmtree(tmp_out_dir)

def process_with_paddleocr_vl(file_path: str) -> Dict[str, Any]:
    """Process document with PaddleOCR-VL backend"""
    output = model_engine.predict(file_path)
    
    structured_data = {
        "pages": [],
        "tables": [],
        "key_value_pairs": [],
        "text_blocks": [],
        "layout_info": []
    }
    
    for idx, res in enumerate(output):
        if not isinstance(res, dict):
            continue
        
        page_data = {"page_index": idx, "elements": []}
        
        # Layout detection
        if 'layout_det_res' in res and res['layout_det_res']:
            layout_res = res['layout_det_res']
            if 'boxes' in layout_res:
                for box in layout_res['boxes']:
                    layout_info = {
                        "type": box.get('label', 'unknown'),
                        "bbox": [float(c) for c in box.get('coordinate', [])],
                        "confidence": float(box.get('score', 0.0))
                    }
                    structured_data["layout_info"].append(layout_info)
        
        # Table extraction
        if 'table_res_list' in res and res['table_res_list']:
            for table_idx, table in enumerate(res['table_res_list']):
                table_data = {
                    "page": idx,
                    "table_index": table_idx,
                    "bbox": table.get('bbox', []) if isinstance(table, dict) else [],
                    "html": table.get('html', '') if isinstance(table, dict) else ''
                }
                structured_data["tables"].append(table_data)
        
        # Text blocks
        if 'parsing_res_list' in res:
            for item in res['parsing_res_list']:
                item_str = str(item)
                
                label = None
                bbox = []
                content = ""
                
                if 'label:' in item_str:
                    label_start = item_str.find('label:') + 6
                    label_end = item_str.find('\n', label_start)
                    label = item_str[label_start:label_end].strip()
                
                if 'content:' in item_str:
                    content_start = item_str.find('content:') + 8
                    content_end = item_str.find('\n#################', content_start)
                    if content_end == -1:
                        content_end = len(item_str)
                    content = item_str[content_start:content_end].strip()
                
                if content:
                    text_block = {
                        "type": label or "text",
                        "bbox": bbox,
                        "content": content
                    }
                    structured_data["text_blocks"].append(text_block)
                    
                    # Extract key-value pairs
                    lines = content.split('\n')
                    for line in lines:
                        if ':' in line or '：' in line:
                            parts = line.replace('：', ':').split(':', 1)
                            if len(parts) == 2:
                                key = parts[0].strip()
                                value = parts[1].strip()
                                if key and value:
                                    structured_data["key_value_pairs"].append({
                                        "key": key,
                                        "value": value,
                                        "page": idx
                                    })
        
        structured_data["pages"].append(page_data)
    
    # Generate markdown and HTML
    markdown_parts = []
    html_parts = ["<html><body>"]
    
    for block in structured_data["text_blocks"]:
        markdown_parts.append(f"## {block['type']}\n\n{block['content']}\n")
        html_parts.append(f"<div class='text-block' data-type='{block['type']}'><p>{block['content']}</p></div>")
    
    for table in structured_data["tables"]:
        if table.get('html'):
            markdown_parts.append(f"\n### Table {table['table_index']}\n\n")
            html_parts.append(table['html'])
    
    html_parts.append("</body></html>")
    
    return {
        "html": "\n".join(html_parts),
        "markdown": "\n".join(markdown_parts),
        "structured_data": structured_data
    }

@app.post("/extract/structured")
async def extract_structured(file: UploadFile = File(...)):
    """
    Unified structured text extraction endpoint
    Works consistently regardless of underlying VLM backend
    """
    if model_type is None:
        raise HTTPException(
            status_code=503,
            detail=f"VLM backend not available. Error: {initialization_error}"
        )
    
    tmp_path = None
    try:
        # Save uploaded file
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        start_time = time.time()
        logger.info(f"Processing file: {file.filename} with {model_type}")
        
        # Process with appropriate backend
        if model_type == "paddleocr-vl":
            result = process_with_paddleocr_vl(tmp_path)
        elif model_type == "marker":
            result = process_with_marker(tmp_path)
        elif model_type == "mineru":
            result = process_with_mineru(tmp_path)
        else:  # paddleocr
            result = process_with_paddleocr(tmp_path)
        
        processing_time_s = time.time() - start_time
        logger.info(f"✅ Processing completed in {processing_time_s:.2f}s")
        
        return {
            "success": True,
            "html": result["html"],
            "markdown": result["markdown"],
            "structured_data": result["structured_data"],
            "processing_time_s": processing_time_s,
            "model": model_type,
            "device": os.environ.get("PADDLEOCR_DEVICE", "cpu")
        }
        
    except Exception as e:
        logger.error(f"Error during processing: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)

@app.get("/")
async def root():
    """Root endpoint with API information"""
    return {
        "name": "Unified VLM API",
        "version": "1.0.0",
        "model": model_type,
        "status": "available" if model_engine else "unavailable",
        "endpoints": {
            "health": "/health",
            "extract": "/extract/structured",
            "docs": "/docs"
        }
    }
