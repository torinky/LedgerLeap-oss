# docker/paddleocr-vl/Dockerfile
# PaddleOCR-VL 0.9B テストコンテナ
# CPU実行可否の検証用

FROM python:3.10-slim

LABEL maintainer="LedgerLeap Development Team"
LABEL description="PaddleOCR-VL 0.9B Test Container for CPU inference validation"

WORKDIR /var/www/html

# システム依存関係のインストール
RUN apt-get update && apt-get install -y \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    wget \
    && rm -rf /var/lib/apt/lists/*

# PaddlePaddle（CPU版）をインストール
# Note: Using latest available CPU version instead of 3.2.0
# 3.2.0 is only available for GPU version
RUN pip install --no-cache-dir paddlepaddle \
    -i https://pypi.tuna.tsinghua.edu.cn/simple

# PaddleOCR with doc-parser（最新版）
RUN pip install --no-cache-dir -U "paddleocr[doc-parser]"

# カスタムsafetensors（試行的）
# Note: This may fail on CPU, which is expected - we'll use default safetensors
# Commenting out for now as it's likely to cause issues on CPU
# RUN pip install --no-cache-dir \
#     https://paddle-whl.bj.bcebos.com/nightly/cpu/safetensors/safetensors-0.6.2.dev0-cp38-abi3-linux_x86_64.whl \
#     || echo "Warning: Custom safetensors installation failed, using default"

# FastAPI関連
RUN pip install --no-cache-dir \
    fastapi==0.104.1 \
    uvicorn[standard]==0.24.0 \
    python-multipart==0.0.6 \
    requests

# アプリケーションファイルをコピー
COPY app_vl.py .

# ポート公開
EXPOSE 8002

# ヘルスチェック
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD python -c "import requests; requests.get('http://localhost:8002/health')" || exit 1

# サーバー起動
CMD ["uvicorn", "app_vl:app", "--host", "0.0.0.0", "--port", "8002"]
