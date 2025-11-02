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
import re
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
        use_gpu=use_gpu,
        use_space_char=True,
        det_db_score_mode='slow',
        det_limit_side_len=960,
        return_word_box=True,
        use_doc_unwarping=True,
        show_log=False
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
    
    # Process results with enhanced structured data extraction
    text_lines = []
    text_blocks = []
    key_value_pairs = []
    html_parts = ["<html><body>"]
    
    if result and result[0]:
        for idx, line in enumerate(result[0]):
            if len(line) >= 2:
                bbox = line[0]
                text_info = line[1]
                text = text_info[0]
                confidence = text_info[1] if len(text_info) > 1 else 1.0
                
                text_lines.append(text)
                html_parts.append(f"<p>{text}</p>")
                
                # Enhanced structured data with bbox and confidence
                text_blocks.append({
                    "type": "text",
                    "content": text,
                    "bbox": [[float(p[0]), float(p[1])] for p in bbox],
                    "confidence": float(confidence),
                    "line_index": idx
                })
                
                # Extract key-value pairs from lines containing colons
                if ':' in text or '：' in text:
                    parts = text.replace('：', ':').split(':', 1)
                    if len(parts) == 2:
                        key = parts[0].strip()
                        value = parts[1].strip()
                        if key and value:
                            key_value_pairs.append({
                                "key": key,
                                "value": value,
                                "confidence": float(confidence),
                                "bbox": [[float(p[0]), float(p[1])] for p in bbox]
                            })
    
    html_parts.append("</body></html>")
    
    return {
        "html": "\n".join(html_parts),
        "markdown": "\n\n".join(text_lines),
        "structured_data": {
            "pages": [{
                "page_index": 0,
                "text_lines": text_lines,
                "line_count": len(text_lines)
            }],
            "text_blocks": text_blocks,
            "tables": [],
            "key_value_pairs": key_value_pairs
        }
    }

def parse_markdown_structure(markdown_text: str) -> Dict[str, Any]:
    """
    Parse Markdown text into structured data compatible with PaddleOCR output format
    
    Extracts:
    - Text blocks with types (header, paragraph, list, table, etc.)
    - Tables (Markdown table format)
    - Key-Value pairs (lines with colons)
    """
    import re
    
    text_blocks = []
    tables = []
    key_value_pairs = []
    
    lines = markdown_text.split('\n')
    current_paragraph = []
    in_table = False
    table_lines = []
    line_index = 0
    
    for idx, line in enumerate(lines):
        stripped = line.strip()
        
        if not stripped:
            # Empty line: flush current paragraph
            if current_paragraph:
                text_blocks.append({
                    "type": "text",
                    "content": '\n'.join(current_paragraph),
                    "line_index": line_index,
                    "confidence": 0.95
                })
                current_paragraph = []
                line_index = idx + 1
            continue
        
        # Header detection
        if stripped.startswith('#'):
            if current_paragraph:
                text_blocks.append({
                    "type": "text",
                    "content": '\n'.join(current_paragraph),
                    "line_index": line_index,
                    "confidence": 0.95
                })
                current_paragraph = []
            
            level = len(re.match(r'^#+', stripped).group())
            header_text = stripped.lstrip('#').strip()
            text_blocks.append({
                "type": f"header_{level}",
                "content": header_text,
                "line_index": idx,
                "confidence": 0.98
            })
            line_index = idx + 1
            continue
        
        # Table detection (Markdown table format)
        if '|' in stripped and not stripped.startswith('!['):
            if not in_table:
                # Start of table
                if current_paragraph:
                    text_blocks.append({
                        "type": "text",
                        "content": '\n'.join(current_paragraph),
                        "line_index": line_index,
                        "confidence": 0.95
                    })
                    current_paragraph = []
                in_table = True
                table_lines = []
                line_index = idx
            
            table_lines.append(stripped)
            continue
        elif in_table:
            # End of table
            if table_lines:
                # Parse table structure
                table_html = parse_markdown_table(table_lines)
                tables.append({
                    "type": "table",
                    "html": table_html,
                    "markdown": '\n'.join(table_lines),
                    "line_index": line_index,
                    "confidence": 0.90
                })
                text_blocks.append({
                    "type": "table",
                    "content": '\n'.join(table_lines),
                    "line_index": line_index,
                    "confidence": 0.90
                })
            in_table = False
            table_lines = []
            line_index = idx
        
        # List detection
        if re.match(r'^[-*+]\s', stripped) or re.match(r'^\d+\.\s', stripped):
            if current_paragraph:
                text_blocks.append({
                    "type": "text",
                    "content": '\n'.join(current_paragraph),
                    "line_index": line_index,
                    "confidence": 0.95
                })
                current_paragraph = []
            
            text_blocks.append({
                "type": "list_item",
                "content": re.sub(r'^[-*+\d.]\s+', '', stripped),
                "line_index": idx,
                "confidence": 0.95
            })
            line_index = idx + 1
            continue
        
        # Key-Value detection (similar to PaddleOCR logic)
        if ':' in stripped or '：' in stripped:
            normalized = stripped.replace('：', ':')
            if normalized.count(':') == 1:
                parts = normalized.split(':', 1)
                if len(parts) == 2:
                    key = parts[0].strip()
                    value = parts[1].strip()
                    # Filter out time-like patterns and ensure meaningful content
                    if (key and value and 
                        len(key) > 1 and len(key) < 50 and 
                        not re.match(r'^\d{1,2}$', key)):  # Not just numbers (time)
                        key_value_pairs.append({
                            "key": key,
                            "value": value,
                            "confidence": 0.92,
                            "line_index": idx
                        })
        
        # Regular paragraph text
        current_paragraph.append(stripped)
    
    # Flush any remaining content
    if current_paragraph:
        text_blocks.append({
            "type": "text",
            "content": '\n'.join(current_paragraph),
            "line_index": line_index,
            "confidence": 0.95
        })
    
    if in_table and table_lines:
        table_html = parse_markdown_table(table_lines)
        tables.append({
            "type": "table",
            "html": table_html,
            "markdown": '\n'.join(table_lines),
            "line_index": line_index,
            "confidence": 0.90
        })
    
    return {
        "text_blocks": text_blocks,
        "tables": tables,
        "key_value_pairs": key_value_pairs
    }

def parse_markdown_table(table_lines: list) -> str:
    """Convert Markdown table to HTML"""
    if not table_lines:
        return ""
    
    html_parts = ["<table>"]
    is_header = True
    
    for line in table_lines:
        # Skip separator lines (e.g., |---|---|)
        if re.match(r'^\|[\s\-:]+\|$', line):
            is_header = False
            continue
        
        cells = [cell.strip() for cell in line.split('|')[1:-1]]  # Remove empty first/last
        
        if is_header:
            html_parts.append("<tr>")
            for cell in cells:
                html_parts.append(f"<th>{cell}</th>")
            html_parts.append("</tr>")
            is_header = False
        else:
            html_parts.append("<tr>")
            for cell in cells:
                html_parts.append(f"<td>{cell}</td>")
            html_parts.append("</tr>")
    
    html_parts.append("</table>")
    return "".join(html_parts)

def process_with_marker(file_path: str) -> Dict[str, Any]:
    """Process document with Marker backend (PDF to Markdown)
    
    Optimized for Japanese OCR with balanced speed/quality settings
    """
    import subprocess
    import tempfile
    import shutil
    
    tmp_out_dir = tempfile.mkdtemp()
    
    try:
        # Optimized DPI for Japanese OCR (balance between speed and accuracy)
        # 96 DPI for layout detection, 400 DPI for Japanese OCR quality
        lowres_dpi = os.environ.get("MARKER_LOWRES_DPI", "96")
        highres_dpi = os.environ.get("MARKER_HIGHRES_DPI", "400")
        
        # Force CPU mode for stability (avoids GPU-related segfaults)
        os.environ["TORCH_DEVICE"] = "cpu"
        
        # Optimal thread count for CPU (use physical cores)
        cpu_count = os.cpu_count() or 4
        os.environ.setdefault("OMP_NUM_THREADS", str(cpu_count))
        os.environ.setdefault("MKL_NUM_THREADS", str(cpu_count))
        
        cmd = [
            "marker_single", file_path, 
            "--output_dir", tmp_out_dir,
            "--lowres_image_dpi", lowres_dpi,
            "--highres_image_dpi", highres_dpi,
            # NOTE: --disable_ocr is NOT used (OCR required for Japanese)
        ]
        
        # Optimized batch size for Japanese text processing
        batch_size = os.environ.get("MARKER_BATCH_SIZE", "2")
        cmd.extend([
            "--layout_batch_size", batch_size,
            "--recognition_batch_size", batch_size,
        ])
        
        # Disable multiprocessing if specified (for stability)
        if os.environ.get("MARKER_DISABLE_MULTIPROCESSING", "false").lower() == "true":
            cmd.append("--disable_multiprocessing")
        
        logger.info(f"Running Marker (Japanese OCR optimized) with command: {' '.join(cmd)}")
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
        
        # Parse Markdown into structured data
        parsed = parse_markdown_structure(markdown_text)
        
        # Generate HTML from text blocks
        html_parts = ["<html><body>"]
        for block in parsed["text_blocks"]:
            block_type = block.get("type", "text")
            content = block.get("content", "")
            
            if block_type.startswith("header_"):
                level = block_type.split("_")[1]
                html_parts.append(f"<h{level}>{content}</h{level}>")
            elif block_type == "table":
                # Table HTML is already in the tables array
                table_data = next((t for t in parsed["tables"] if t.get("markdown") == content), None)
                if table_data:
                    html_parts.append(table_data.get("html", ""))
            elif block_type == "list_item":
                html_parts.append(f"<li>{content}</li>")
            else:
                html_parts.append(f"<p>{content}</p>")
        html_parts.append("</body></html>")
        
        return {
            "html": "\n".join(html_parts),
            "markdown": markdown_text,
            "structured_data": {
                "pages": [{
                    "page_index": 0,
                    "text_lines": [b.get("content", "") for b in parsed["text_blocks"]],
                    "line_count": len(parsed["text_blocks"])
                }],
                "text_blocks": parsed["text_blocks"],
                "tables": parsed["tables"],
                "key_value_pairs": parsed["key_value_pairs"]
            }
        }
    finally:
        if os.path.exists(tmp_out_dir):
            shutil.rmtree(tmp_out_dir)

def process_with_mineru(file_path: str) -> Dict[str, Any]:
    """Process document with MinerU backend (PDF to Markdown)
    
    Using default settings (already optimized internally)
    """
    import subprocess
    import tempfile
    import shutil
    import glob
    
    tmp_out_dir = tempfile.mkdtemp()
    
    try:
        # Keep default settings - MinerU CLI is already optimized
        # Adding environment variables caused 32% slowdown in tests
        cmd = ["mineru", "-p", file_path, "-o", tmp_out_dir]
        
        logger.info(f"Running MinerU (default settings) with command: {' '.join(cmd)}")
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
        
        # Parse Markdown into structured data
        parsed = parse_markdown_structure(markdown_content)
        
        # Generate HTML from text blocks
        html_parts = ["<html><body>"]
        for block in parsed["text_blocks"]:
            block_type = block.get("type", "text")
            content = block.get("content", "")
            
            if block_type.startswith("header_"):
                level = block_type.split("_")[1]
                html_parts.append(f"<h{level}>{content}</h{level}>")
            elif block_type == "table":
                # Table HTML is already in the tables array
                table_data = next((t for t in parsed["tables"] if t.get("markdown") == content), None)
                if table_data:
                    html_parts.append(table_data.get("html", ""))
            elif block_type == "list_item":
                html_parts.append(f"<li>{content}</li>")
            else:
                html_parts.append(f"<p>{content}</p>")
        html_parts.append("</body></html>")
        
        return {
            "html": "\n".join(html_parts),
            "markdown": markdown_content,
            "structured_data": {
                "pages": [{
                    "page_index": 0,
                    "text_lines": [b.get("content", "") for b in parsed["text_blocks"]],
                    "line_count": len(parsed["text_blocks"])
                }],
                "text_blocks": parsed["text_blocks"],
                "tables": parsed["tables"],
                "key_value_pairs": parsed["key_value_pairs"]
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
