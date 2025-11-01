# docker/paddleocr-vl/app_vl.py
"""
PaddleOCR-VL 0.9B Test API
CPU実行可否検証用の最小実装
"""
from fastapi import FastAPI, File, UploadFile, HTTPException
import logging
import time
from pathlib import Path
import tempfile
import os

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="PaddleOCR-VL Test API", version="0.1.0")

pipeline = None
initialization_error = None

@app.on_event("startup")
async def startup_event():
    global pipeline, initialization_error
    logger.info("=" * 80)
    device = os.environ.get("PADDLEOCR_DEVICE", "cpu")
    logger.info(f'Attempting to initialize PaddleOCR-VL on {device}...')
    logger.info("=" * 80)
    
    try:
        from paddleocr import PaddleOCRVL
        
        logger.info("PaddleOCRVL module imported successfully")
        
        # CPU/GPU版での初期化を試行
        logger.info(f'Initializing PaddleOCRVL with device={device}...')
        pipeline = PaddleOCRVL(
            device=device,
            use_doc_orientation_classify=True,
            use_layout_detection=True,
            use_doc_unwarping=False,
            use_chart_recognition=False
        )
        
        logger.info("=" * 80)
        logger.info("✅ SUCCESS! PaddleOCR-VL initialized on CPU!")
        logger.info("=" * 80)
        
    except Exception as e:
        error_msg = str(e)
        initialization_error = error_msg
        logger.error("=" * 80)
        logger.error(f"❌ FAILED to initialize PaddleOCR-VL")
        logger.error(f"Error: {error_msg}")
        logger.error("=" * 80)
        
        # エラーの種類を判定
        if "safetensors" in error_msg.lower():
            logger.error("→ safetensors互換性問題")
        elif "gpu" in error_msg.lower() or "cuda" in error_msg.lower():
            logger.error("→ GPU/CUDA関連エラー（CPU非対応の可能性）")
        elif "memory" in error_msg.lower():
            logger.error("→ メモリ不足")
        else:
            logger.error("→ その他のエラー")
        
        pipeline = None

@app.get("/health")
async def health_check():
    """ヘルスチェックエンドポイント"""
    if pipeline is None:
        raise HTTPException(
            status_code=503,
            detail={"status": "failed", "model": "PaddleOCR-VL-0.9B", "error": initialization_error or "Unknown error", "message": "PaddleOCR-VL is not available"}
        )
    
    return {
        "status": "healthy",
        "model": "PaddleOCR-VL-0.9B",
        "device": "cpu",
        "message": "PaddleOCR-VL is ready"
    }

@app.post("/extract/structured")
async def extract_structured(file: UploadFile = File(...)):
    """
    構造化テキスト抽出エンドポイント（テスト版）
    """
    if pipeline is None:
        raise HTTPException(
            status_code=503,
            detail=f"PaddleOCR-VL is not available. Error: {initialization_error}"
        )
    
    tmp_path = None
    try:
        # ファイルを一時保存
        with tempfile.NamedTemporaryFile(delete=False, suffix=Path(file.filename).suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name

        start_time = time.time()
        logger.info(f"Processing file: {file.filename}")
        
        # PaddleOCR-VLで処理
        output = pipeline.predict(tmp_path)
        
        # 結果を処理
        markdown_list = []
        for res in output:
            md_info = res.markdown
            if md_info and 'markdown' in md_info:
                markdown_list.append(md_info['markdown'])
        
        # 全ページを結合
        if len(markdown_list) > 1:
            markdown_text = pipeline.concatenate_markdown_pages(
                [{'markdown': md} for md in markdown_list]
            )
        elif len(markdown_list) == 1:
            markdown_text = markdown_list[0]
        else:
            markdown_text = ""
        
        # HTMLへの簡易変換
        html_output = f"<html><body>\n{markdown_text}\n</body></html>"
        
        processing_time_s = time.time() - start_time
        logger.info(f"✅ Processing completed in {processing_time_s:.2f}s")
        
        return {
            "success": True,
            "html": html_output,
            "markdown": markdown_text,
            "processing_time_s": processing_time_s,
            "model": "PaddleOCR-VL-0.9B",
            "device": "cpu"
        }
        
    except Exception as e:
        logger.error(f"Error during processing: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.unlink(tmp_path)

@app.get("/")
async def root():
    """ルートエンドポイント"""
    return {
        "name": "PaddleOCR-VL Test API",
        "version": "0.1.0",
        "status": "available" if pipeline else "unavailable",
        "endpoints": {
            "health": "/health",
            "extract": "/extract/structured",
            "docs": "/docs"
        }
    }
