# dockerfile
FROM python:3.10-slim

LABEL maintainer="LedgerLeap Development Team"
LABEL description="PaddleOCR-VL 0.9B Test Container for CPU inference validation"

WORKDIR /var/www/html
ENV PYTHONPATH=/var/www/html

# システム依存関係のインストール
RUN apt-get update && apt-get install -y --no-install-recommends \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    wget \
    curl \
    && rm -rf /var/lib/apt/lists/*

# PaddlePaddle（CPU版）をインストール
RUN pip install --no-cache-dir paddlepaddle \
    -i https://pypi.tuna.tsinghua.edu.cn/simple

# PaddleNLPとsafetensorsをインストール（PaddleOCR-VLの依存関係を解決）
RUN pip install --no-cache-dir paddlenlp safetensors

# PaddleOCR with doc-parser（最新版）
RUN pip install --no-cache-dir "paddleocr[doc-parser]"

# FastAPI関連
RUN pip install --no-cache-dir \
    fastapi==0.104.1 \
    uvicorn[standard]==0.24.0 \
    python-multipart==0.0.6 \
    requests

# アプリケーションファイルをコピー
COPY app_vl.py .

# ビルド時に import チェック（依存不足や構文エラーを早期検出）
RUN python -c "import importlib, sys; importlib.import_module('app_vl')"

# ポート公開
EXPOSE 8002

# ヘルスチェック（curl）
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8002/health || exit 1

# サーバー起動（python -m uvicorn でモジュール解決を安定化）
CMD ["python", "-m", "uvicorn", "app_vl:app", "--host", "0.0.0.0", "--port", "8002"]
