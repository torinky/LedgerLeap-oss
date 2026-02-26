import os
import shutil
import tempfile
import time
import subprocess
import glob
from fastapi import FastAPI, UploadFile, File, HTTPException
from typing import Dict, Any

# Force CPU execution
os.environ['CUDA_VISIBLE_DEVICES'] = ''
os.environ['DEVICE_MODE'] = 'cpu'

app = FastAPI(title="MinerU VLM Service", version="1.0.0")

@app.get("/health")
def health_check() -> Dict[str, str]:
    """Health check endpoint"""
    return {"status": "healthy", "model": "MinerU", "backend": "CPU"}

@app.post("/extract/structured")
async def extract_structured_data(file: UploadFile = File(...)) -> Dict[str, Any]:
    """
    Extract structured data from uploaded PDF file using MinerU
    Returns markdown formatted text
    """
    start_time = time.time()

    with tempfile.TemporaryDirectory() as temp_dir:
        input_path = os.path.join(temp_dir, file.filename)
        output_dir = os.path.join(temp_dir, "output")
        os.makedirs(output_dir, exist_ok=True)

        # Save uploaded file
        with open(input_path, "wb") as buffer:
            shutil.copyfileobj(file.file, buffer)

        try:
            # Call mineru CLI (MinerU v2.6+)
            command = [
                "mineru",
                "-p", input_path,
                "-o", output_dir,
            ]
            
            result = subprocess.run(
                command,
                capture_output=True,
                text=True,
                check=False,
                timeout=300,
                cwd=temp_dir  # Run from temp directory
            )
            
            # Log output for debugging
            print(f"DEBUG: MinerU return code: {result.returncode}")
            if result.stdout:
                print("MinerU STDOUT:", result.stdout)
            if result.stderr:
                print("MinerU STDERR:", result.stderr)
            
            # Check if MinerU failed
            if result.returncode != 0:
                raise HTTPException(
                    status_code=500,
                    detail=f"MinerU processing failed with code {result.returncode}: {result.stderr or result.stdout}"
                )
            
            # DEBUG: List all files created by MinerU
            import subprocess as sp
            all_output_files = sp.run(['find', output_dir, '-type', 'f'], capture_output=True, text=True).stdout
            print(f"DEBUG: All files in output_dir:\n{all_output_files}")
            
            # Also check temp_dir in case MinerU outputs there
            all_temp_files = sp.run(['find', temp_dir, '-type', 'f', '-name', '*.md'], capture_output=True, text=True).stdout
            print(f"DEBUG: All markdown files in temp_dir:\n{all_temp_files}")

        except subprocess.TimeoutExpired:
            raise HTTPException(
                status_code=504,
                detail="MinerU processing timeout (>5 minutes)"
            )
        except subprocess.CalledProcessError as e:
            print("MinerU Error STDOUT:", e.stdout)
            print("MinerU Error STDERR:", e.stderr)
            raise HTTPException(
                status_code=500,
                detail=f"MinerU processing failed: {e.stderr or e.stdout}"
            )
        except FileNotFoundError:
            raise HTTPException(
                status_code=500,
                detail="mineru command not found. Installation error."
            )

        # Find output markdown file (MinerU may create various output structures)
        markdown_content = ""
        
        # Search patterns for markdown files
        base_name = os.path.splitext(file.filename)[0]
        search_patterns = [
            os.path.join(output_dir, f"{base_name}.md"),
            os.path.join(output_dir, "**", "*.md"),
        ]
        
        print(f"DEBUG: output_dir = {output_dir}")
        print(f"DEBUG: base_name = {base_name}")
        print(f"DEBUG: search_patterns = {search_patterns}")
        
        for pattern in search_patterns:
            md_files = glob.glob(pattern, recursive=True)
            print(f"DEBUG: pattern={pattern}, found={len(md_files)} files")
            if md_files:
                # Use the first found markdown file
                with open(md_files[0], "r", encoding="utf-8") as f:
                    markdown_content = f.read()
                print(f"Found markdown output: {md_files[0]}")
                break
        
        if not markdown_content:
            # List all files in output directory for debugging
            all_files = glob.glob(os.path.join(output_dir, "**", "*"), recursive=True)
            print(f"No markdown found. Output directory contents: {all_files}")
            raise HTTPException(
                status_code=500,
                detail="MinerU completed but no markdown output was generated"
            )

        processing_time = time.time() - start_time

        return {
            "success": True,
            "html": "",
            "markdown": markdown_content,
            "processing_time_s": round(processing_time, 2),
            "backend": "mineru-cpu",
        }
