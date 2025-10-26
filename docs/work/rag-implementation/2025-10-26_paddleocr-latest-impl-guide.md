# PaddleOCRVLによる日本語構造化抽出 実装ガイド (改訂版)

**作成日:** 2025年10月26日
**関連ドキュメント:** [Phase 0: VLM追加調査計画書](./2025-10-26_phase0-vlm-additional-investigation-plan.md)

---

## 1. 目的とアーキテクチャ

`PaddleOCR`の依存関係問題の調査過程で、より新しく高機能な統合パイプラインである**`PaddleOCRVL`**の存在が判明した。本ガイドは、当初の`PP-Structure`を利用する古いアプローチを破棄し、この`PaddleOCRVL`を用いてPDF/画像からMarkdown形式で構造化データを抽出するAPIを実装するための、最新の公式情報に基づく手順を示す。

このアプローチは、よりシンプルで堅牢な実装を可能にする。

## 2. 最終的なコンテナ構成

度重なる依存関係エラーの経験から、`pip`による依存関係解決の不安定さを回避するため、以下の構成を最終案とする。

### 2.1. `requirements.txt`

NumPyのABI非互換問題を回避するため、`numpy<2`を指定することが極めて重要である。`paddleocr[doc-parser]`が、`PaddleOCRVL`に必要な依存関係をインストールする。

```
fastapi==0.104.1
uvicorn[standard]==0.24.0
python-multipart==0.0.6

# paddleocr[doc-parser] will handle its own dependencies.
paddlepaddle==2.6.1
paddleocr[doc-parser]==2.7.3

# CRITICAL: Force numpy to a version compatible with older libraries.
numpy<2

# Other necessary libraries
PyMuPDF==1.19.0
opencv-python-headless
Pillow==10.1.0
```

### 2.2. `Dockerfile`

`python:3.10-slim`をベースとし、上記の`requirements.txt`をインストールするだけのシンプルな構成とする。

```dockerfile
FROM python:3.10-slim

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    && rm -rf /var/lib/apt/lists/*

COPY ./docker/paddle/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

EXPOSE 8000
CMD ["uvicorn", "docker.paddle.app:app", "--host", "0.0.0.0", "--port", "8000"]
```

### 2.3. `app.py` (`PaddleOCRVL`版)

公式ドキュメントのサンプルコードに基づき、`PaddleOCRVL`パイプラインを初期化し、Markdownを生成するAPIを実装する。

```python
# docker/paddle/app.py
from fastapi import FastAPI, File, UploadFile, HTTPException
from paddleocr import PaddleOCRVL
from pathlib import Path
import tempfile
import os
import logging
import time

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI(title="PaddleOCR-VL API", version="3.0.0")

pipeline = None

@app.on_event("startup")
async def startup_event():
    global pipeline
    logger.info("Initializing PaddleOCR-VL pipeline...")
    # CPUで実行するために device='cpu' を指定
    pipeline = PaddleOCRVL(device='cpu')
    logger.info("PaddleOCR-VL pipeline initialized successfully.")

@app.get("/health")
async def health_check():
    if pipeline is None:
        raise HTTPException(status_code=503, detail="Model not initialized.")
    return {"status": "healthy", "model": "PaddleOCR-VL"}

@app.post("/extract/markdown")
async def extract_markdown(file: UploadFile = File(...)):
    if pipeline is None:
        raise HTTPException(status_code=500, detail="Model is not available.")

    try:
        suffix = Path(file.filename).suffix
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
            content = await file.read()
            tmp.write(content)
            tmp_path = tmp.name
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Failed to handle uploaded file: {e}")

    start_time = time.time()
    markdown_output = ""
    
    try:
        logger.info(f"Starting prediction for {tmp_path}")
        output = pipeline.predict(input=tmp_path)
        logger.info("Prediction finished.")

        if suffix.lower() == '.pdf':
            markdown_list = [res.markdown for res in output]
            markdown_output = pipeline.concatenate_markdown_pages(markdown_list)
        else:
            if output:
                markdown_output = output[0].markdown['markdown']

        processing_time_s = time.time() - start_time

        return {
            "success": True,
            "markdown": markdown_output,
            "processing_time_s": processing_time_s,
        }

    except Exception as e:
        logger.error(f"An error occurred during prediction: {e}", exc_info=True)
        raise HTTPException(status_code=500, detail=str(e))
    finally:
        if os.path.exists(tmp_path):
            os.unlink(tmp_path)
```

## 3. 今後の課題

1.  **ビルドと起動の再検証:** 上記の最終構成でコンテナが正常にビルド・起動できることを確認する。
2.  **動作テスト:** `curl`やPythonクライアントを用いて、`/extract/markdown`エンドポイントが期待通りに動作するかをテストする。
3.  **Laravelとの連携:** `vlm`サービスとして起動したこのコンテナに、LaravelアプリケーションからHTTPリクエストを送信するクライアント部分を実装する。

---