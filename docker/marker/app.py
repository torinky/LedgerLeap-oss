import traceback
from fastapi import FastAPI, File, UploadFile, HTTPException
import logging
import time
from pathlib import Path
import tempfile
import os
import subprocess

# ロギング設定
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="Marker VLM API (CLI Wrapper)", version="2.0.0")

@app.get("/health")
async def health_check():
    # Check if marker_single command is available
    try:
        subprocess.run(["marker_single", "--help"], check=True, capture_output=True)
        return {"status": "healthy", "model": "Marker (CLI)"}
    except (subprocess.CalledProcessError, FileNotFoundError) as e:
        logger.error(f"marker_single command not found or failed: {e}")
        raise HTTPException(status_code=503, detail="marker_single command is not available.")

@app.post("/extract/markdown")
async def extract_markdown(file: UploadFile = File(...)):
    if Path(file.filename).suffix.lower() != ".pdf":
        raise HTTPException(status_code=400, detail="Marker only supports PDF files.")

    tmp_in_path = None
    tmp_out_dir = None
    try:
        # Create a temporary directory for output
        tmp_out_dir = tempfile.mkdtemp()
        
        # Save the uploaded file temporarily
        with tempfile.NamedTemporaryFile(delete=False, suffix=".pdf") as tmp_in:
            content = await file.read()
            tmp_in.write(content)
            tmp_in_path = tmp_in.name

        logger.info(f"Marker: Processing file via CLI: {file.filename}")
        start_time = time.time()
        
        output_md_path = os.path.join(tmp_out_dir, f"{Path(tmp_in_path).stem}.md")

        # Execute the marker_single command
        result = subprocess.run(
            ["marker_single", tmp_in_path, output_md_path],
            check=True,
            capture_output=True,
            text=True
        )
        
        logger.info(f"Marker CLI stdout: {result.stdout}")
        logger.error(f"Marker CLI stderr: {result.stderr}")

        processing_time_s = time.time() - start_time
        
        # Find the output markdown file
        output_md_files = list(Path(tmp_out_dir).glob("*.md"))
        if not output_md_files:
            raise ValueError("Marker CLI did not produce an output markdown file.")
        
        output_md_path = output_md_files[0]
        with open(output_md_path, "r", encoding="utf-8") as f:
            markdown_text = f.read()

        logger.info(f"Marker: Conversion completed in {processing_time_s:.2f}s.")
        return {
            "success": True,
            "markdown": markdown_text,
            "processing_time_s": processing_time_s,
        }
    except subprocess.CalledProcessError as e:
        logger.error(f"Marker CLI command failed with exit code {e.returncode}")
        logger.error(f"Stdout: {e.stdout}")
        logger.error(f"Stderr: {e.stderr}")
        raise HTTPException(status_code=500, detail=f"Marker CLI failed: {e.stderr}")
    except Exception as e:
        logger.error(f"Marker conversion failed: {e}")
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if tmp_in_path and os.path.exists(tmp_in_path):
            os.unlink(tmp_in_path)
        if tmp_out_dir and os.path.exists(tmp_out_dir):
            import shutil
            shutil.rmtree(tmp_out_dir)

@app.get("/")
async def root():
    return {
        "message": "Marker VLM API Server (CLI Wrapper)",
        "endpoints": {
            "health": "/health",
            "extract_markdown": "POST /extract/markdown"
        }
    }