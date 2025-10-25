# docker/marker/Dockerfile
# ARM64ネイティブイメージを明示的に指定
FROM --platform=linux/arm64/v8 python:3.10-slim

# 作業ディレクトリ
WORKDIR /app

# システム依存パッケージ
RUN apt-get update && apt-get install -y \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    && rm -rf /var/lib/apt/lists/*

# Pythonパッケージ
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# アプリケーションコード
COPY app.py .

# ポート公開
EXPOSE 8000

# 起動 (Pythonのバッファリングを無効化)
ENV PYTHONUNBUFFERED=1

# Marker最適化設定（環境変数）
ENV MARKER_LOWRES_DPI=72
ENV MARKER_HIGHRES_DPI=144

CMD ["uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8000"]

