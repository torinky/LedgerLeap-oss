# PaddleOCRVLによる日本語構造化抽出 実装ガイド (改訂版)

**作成日:** 2025年10月26日  
**最終更新:** 2025年10月26日  
**ステータス:** ✅ **実装完了**  
**関連ドキュメント:** 
- [Phase 0: VLM追加調査計画書](./2025-10-26_phase0-vlm-additional-investigation-plan.md)
- [PaddleOCRVL API実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md)

---

## 📝 更新履歴

**2025-10-26 夜:** 
- ✅ 実装完了
- ✅ テストスイート整備完了
- 🔄 デプロイ・実機テスト待ち

詳細は [実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md) を参照。

---

## 1. 目的とアーキテクチャ

`PaddleOCR`の依存関係問題の調査過程で、より新しく高機能な統合パイプラインである**`PaddleOCRVL`**の存在が判明した。本ガイドは、当初の`PP-Structure`を利用する古いアプローチを破棄し、この`PaddleOCRVL`を用いてPDF/画像からMarkdown形式で構造化データを抽出するAPIを実装するための、最新の公式情報に基づく手順を示す。

このアプローチは、よりシンプルで堅牢な実装を可能にする。

## 4. 以下は初期の設計案（参考）

以下の内容は、実装前の設計段階で作成されたものです。
実際の実装内容は上記の「実装完了記録」を参照してください。

---

## 4.1. 最終的なコンテナ構成（初期設計）

度重なる依存関係エラーの経験から、`pip`による依存関係解決の不安定さを回避するため、以下の構成を最終案とする。

### 4.2. requirements.txt（初期設計）

**注:** 実際の実装では、さらに簡略化されています。

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

### 4.3. Dockerfile（初期設計）

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

### 4.4. app.py（初期設計案）

**注:** 実際の実装では、より詳細なエラーハンドリングとMarkdown→HTML変換が追加されています。

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

## 3. 実装状況

### 3.1. ✅ 実装完了（2025-10-26）

上記の設計に基づき、以下の実装が完了した:

- ✅ `docker/paddle/app.py` - PaddleOCRVLパイプラインを使用した完全な実装
- ✅ `docker/paddle/requirements.txt` - 依存関係の最適化
- ✅ `tests/Feature/Vlm/PaddleOcrVlmTest.php` - 包括的なテストスイート
- ✅ Markdown→HTML変換関数の実装
- ✅ PDF複数ページ処理の実装

詳細は以下を参照:
- **[PaddleOCRVL API実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md)** ← **最新の実装ドキュメント**

### 3.2. 次のステップ

1.  **コンテナの再ビルド:** 実装した変更を反映させるため、VLMコンテナを再ビルド
2.  **テストの実行:** 全テストケースが正常に動作することを確認
3.  **Laravel統合:** アプリケーション側からのAPI呼び出し実装
4.  **性能評価:** 実際のワークロードでの性能測定

---

## 4. 以下は初期の設計案（参考）

以下の内容は、実装前の設計段階で作成されたものです。
実際の実装内容は上記の「実装完了記録」を参照してください。

---

---

## 5. 参考情報

### 5.1. 実装時に参考にした公式ドキュメント

- [PaddleOCR-VL Python API Integration](https://github.com/PaddlePaddle/PaddleOCR)
- Basic Image Processing サンプルコード
- PDF Document Processing サンプルコード

### 5.2. 依存関係の経緯

度重なる依存関係エラーの経験から、最終的にバージョン固定を最小限にする方針に変更した。
詳細な試行錯誤の記録は [Phase 0追加調査計画書](./2025-10-26_phase0-vlm-additional-investigation-plan.md) の「6. VLM実装の試行錯誤の記録」を参照。

---