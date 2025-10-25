import traceback
from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
import logging
import time
from pathlib import Path
import tempfile
import os
import subprocess
import asyncio
from contextlib import asynccontextmanager

# ロギング設定（即座にフラッシュ）
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(name)s: %(message)s',
    handlers=[
        logging.StreamHandler()
    ]
)
# StreamHandlerのフラッシュを強制
for handler in logging.root.handlers:
    handler.flush = lambda: None
    
logger = logging.getLogger(__name__)

# グローバル状態管理
class AppState:
    def __init__(self):
        self.is_ready = False
        self.is_processing = False
        self.warmup_completed = False

app_state = AppState()

@asynccontextmanager
async def lifespan(app: FastAPI):
    """アプリケーション起動時にモデルをウォームアップ"""
    logger.info("Starting model warmup...")
    try:
        # ダミーPDFでモデルを事前ロード
        await warmup_models()
        app_state.is_ready = True
        app_state.warmup_completed = True
        logger.info("✅ Model warmup completed. Ready to accept requests.")
    except Exception as e:
        logger.error(f"❌ Warmup failed: {e}")
        app_state.is_ready = False
    
    yield
    
    logger.info("Shutting down...")

app = FastAPI(
    title="Marker VLM API (CLI Wrapper)", 
    version="3.0.0",
    lifespan=lifespan
)

async def warmup_models():
    """モデルを事前ロードするためのウォームアップ処理"""
    logger.info("Creating dummy PDF for warmup...")
    
    # 最小限のダミーPDFを作成（1ページ、テキストのみ）
    dummy_pdf = b"""%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj
3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj
4 0 obj<</Length 44>>stream
BT /F1 12 Tf 100 700 Td (Warmup) Tj ET
endstream endobj
5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj
xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000262 00000 n 
0000000356 00000 n 
trailer<</Size 6/Root 1 0 R>>
startxref
444
%%EOF"""
    
    tmp_in_path = None
    tmp_out_dir = None
    
    try:
        tmp_out_dir = tempfile.mkdtemp()
        
        with tempfile.NamedTemporaryFile(delete=False, suffix=".pdf") as tmp_in:
            tmp_in.write(dummy_pdf)
            tmp_in_path = tmp_in.name
        
        logger.info(f"Running warmup conversion...")
        
        # 最適化設定を適用
        lowres_dpi = os.getenv("MARKER_LOWRES_DPI", "96")
        highres_dpi = os.getenv("MARKER_HIGHRES_DPI", "192")
        
        # ウォームアップ実行（モデルダウンロード + 初回ロード）
        result = subprocess.run(
            [
                "marker_single", tmp_in_path, "--output_dir", tmp_out_dir,
                "--lowres_image_dpi", lowres_dpi,
                "--highres_image_dpi", highres_dpi
            ],
            check=True,
            capture_output=True,  # ウォームアップ時はログを抑制
            text=True,
            timeout=300  # 5分でタイムアウト
        )
        
        logger.info(f"Warmup conversion completed successfully (DPI: low={lowres_dpi}, high={highres_dpi})")
        
    except subprocess.TimeoutExpired:
        logger.warning("Warmup timed out, but models may have been downloaded")
    except Exception as e:
        logger.error(f"Warmup failed: {e}")
        raise
    finally:
        if tmp_in_path and os.path.exists(tmp_in_path):
            os.unlink(tmp_in_path)
        if tmp_out_dir and os.path.exists(tmp_out_dir):
            import shutil
            shutil.rmtree(tmp_out_dir)

@app.get("/health")
async def health_check():
    """ヘルスチェック - モデルのロード状態も確認"""
    try:
        subprocess.run(["marker_single", "--help"], check=True, capture_output=True)
        
        return {
            "status": "healthy" if app_state.is_ready else "warming_up",
            "model": "Marker (CLI)",
            "ready": app_state.is_ready,
            "processing": app_state.is_processing,
            "warmup_completed": app_state.warmup_completed
        }
    except (subprocess.CalledProcessError, FileNotFoundError) as e:
        logger.error(f"marker_single command not found or failed: {e}")
        raise HTTPException(status_code=503, detail="marker_single command is not available.")

@app.post("/extract/markdown")
async def extract_markdown(
    file: UploadFile = File(...),
    max_pages: int = None  # ページ数制限（オプション）
):
    """PDFをMarkdownに変換（ページ分割対応）"""
    
    # ウォームアップ完了チェック
    if not app_state.is_ready:
        return JSONResponse(
            status_code=503,
            content={
                "error": "Service is warming up. Please retry in a few moments.",
                "warmup_completed": app_state.warmup_completed,
                "retry_after": 30  # 30秒後にリトライ推奨
            },
            headers={"Retry-After": "30"}
        )
    
    # 処理中チェック（単一インスタンスでの同時処理を防ぐ）
    if app_state.is_processing:
        return JSONResponse(
            status_code=503,
            content={
                "error": "Another conversion is in progress. Please retry later.",
                "retry_after": 60  # 60秒後にリトライ推奨
            },
            headers={"Retry-After": "60"}
        )
    
    if Path(file.filename).suffix.lower() != ".pdf":
        raise HTTPException(status_code=400, detail="Marker only supports PDF files.")

    app_state.is_processing = True
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

        # コマンド引数を構築
        cmd = ["marker_single", tmp_in_path, "--output_dir", tmp_out_dir]
        
        # パフォーマンス最適化オプション
        lowres_dpi = os.getenv("MARKER_LOWRES_DPI", "96")
        highres_dpi = os.getenv("MARKER_HIGHRES_DPI", "192")
        cmd.extend([
            "--lowres_image_dpi", lowres_dpi,
            "--highres_image_dpi", highres_dpi
        ])
        logger.info(f"Using DPI settings: lowres={lowres_dpi}, highres={highres_dpi}")
        
        # ページ数制限が指定されている場合
        if max_pages:
            cmd.extend(["--page_range", f"0-{max_pages-1}"])
            logger.info(f"Processing only first {max_pages} pages")
        
        # Execute the marker_single command with output_dir option
        # stdout/stderrをリアルタイムで出力
        result = subprocess.run(
            cmd,
            check=True,
            capture_output=False,  # リアルタイム出力を有効化
            text=True,
            timeout=600  # 10分でタイムアウト
        )

        processing_time_s = time.time() - start_time
        
        # Find the output markdown file (Marker outputs in subdirectories)
        logger.info(f"Searching for output files in: {tmp_out_dir}")
        all_items = list(Path(tmp_out_dir).iterdir())
        logger.info(f"Found items in output dir: {all_items}")
        
        # Check if there's a subdirectory
        if all_items and all_items[0].is_dir():
            sub_dir = all_items[0]
            logger.info(f"Found subdirectory: {sub_dir}, checking contents...")
            all_items = list(sub_dir.iterdir())
            logger.info(f"Found items in subdirectory: {all_items}")
        
        output_md_files = [f for f in all_items if f.is_file()]
        logger.info(f"Filtered files: {output_md_files}")
        
        if not output_md_files:
            raise ValueError("Marker CLI did not produce an output file.")
        
        output_md_path = output_md_files[0]
        logger.info(f"Using output file: {output_md_path}")
        with open(output_md_path, "r", encoding="utf-8") as f:
            markdown_text = f.read()

        logger.info(f"Marker: Conversion completed in {processing_time_s:.2f}s.")
        return {
            "success": True,
            "markdown": markdown_text,
            "processing_time_s": processing_time_s,
            "file_size_bytes": len(markdown_text),
            "max_pages_limit": max_pages
        }
    except subprocess.TimeoutExpired:
        logger.error("Marker CLI timed out after 600 seconds")
        raise HTTPException(status_code=504, detail="Conversion timed out. Try processing fewer pages.")
    except subprocess.CalledProcessError as e:
        logger.error(f"Marker CLI command failed with exit code {e.returncode}")
        raise HTTPException(status_code=500, detail=f"Marker CLI failed with exit code {e.returncode}")
    except Exception as e:
        logger.error(f"Marker conversion failed: {e}")
        logger.error(traceback.format_exc())
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        app_state.is_processing = False
        if tmp_in_path and os.path.exists(tmp_in_path):
            os.unlink(tmp_in_path)
        if tmp_out_dir and os.path.exists(tmp_out_dir):
            import shutil
            shutil.rmtree(tmp_out_dir)

@app.get("/")
async def root():
    return {
        "message": "Marker VLM API Server (CLI Wrapper) v3.0",
        "features": [
            "Model pre-loading on startup",
            "Processing queue management",
            "Page range support"
        ],
        "endpoints": {
            "health": "GET /health - Check service status and readiness",
            "extract_markdown": "POST /extract/markdown - Convert PDF to Markdown (supports max_pages parameter)"
        },
        "status": {
            "ready": app_state.is_ready,
            "processing": app_state.is_processing
        }
    }