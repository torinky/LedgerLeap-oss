# paddlepaddle/paddleの公式GPUイメージをベースイメージとして使用
# これにはCUDA、cuDNN、およびGPUサポート付きのPaddlePaddleが含まれています。
FROM paddlepaddle/paddle:3.2.0-gpu-cuda12.6-cudnn9.5

# コンテナ内の作業ディレクトリを設定
WORKDIR /app
ENV PYTHONPATH=/app

# 一般的なユーティリティと依存関係をインストール
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    wget \
    curl \
    && rm -rf /var/lib/apt/lists/*

# PaddleOCR-VLとそのドキュメント解析の依存関係をインストール
# "[doc-parser]"オプションは、PaddleOCR-VLの高度な機能に必要な追加パッケージをインストールします。
# 2025年11月時点の最新版を使用（bfloat16問題の修正を含む）
RUN pip install --no-cache-dir -U "paddleocr[doc-parser]"

# CRITICAL FIX (2025-11): PaddleX 3.3.5+ required to fix bfloat16 weight format issue
# This resolves "framework paddle is invalid" error with PaddleOCR-VL-0.9B
RUN pip install --no-cache-dir -U paddlex

# Install special safetensors wheel with PaddlePaddle framework support
# Standard pip safetensors does NOT support Paddle framework
RUN pip install --no-cache-dir \
    https://paddle-whl.bj.bcebos.com/nightly/cu126/safetensors/safetensors-0.6.2.dev0-cp38-abi3-linux_x86_64.whl

# FastAPI関連のライブラリをインストール
RUN pip install --no-cache-dir \
    fastapi==0.104.1 \
    uvicorn[standard]==0.24.0 \
    python-multipart==0.0.6 \
    requests

# アプリケーションファイルをコピー
# Note: unified_api.py will be available via volume mount in actual deployment

# ポートを公開
EXPOSE 8000

# ヘルスチェック
HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# サーバーを起動
# Use unified API that automatically detects VLM_MODEL environment variable
WORKDIR /var/www/html
CMD ["python", "-m", "uvicorn", "docker.paddle.unified_api:app", "--host", "0.0.0.0", "--port", "8000"]