# docker/paddle/Dockerfile
# Supports: paddleocr (PP-OCR, CPU), paddleocr-vl-cpu (PaddleOCR-VL-1.6, CPU),
#           paddleocr-vl (GPU, PaddleOCRVL native), marker, mineru
FROM python:3.10-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

RUN pip install --upgrade pip

# CPU版 PaddlePaddle を paddleocr より先に明示インストール
# これにより GPU 版自動検出を防止
# Ref: https://www.paddlepaddle.org.cn/en/install/quick?docurl=/documentation/docs/en/develop/install/pip/linux-pip_en.html
RUN pip install paddlepaddle --index-url https://www.paddlepaddle.org.cn/packages/stable/cpu/

COPY requirements.txt .
RUN pip install --retries 10 --timeout 300 -r requirements.txt

EXPOSE 8000

CMD ["uvicorn", "docker.paddle.unified_api:app", "--host", "0.0.0.0", "--port", "8000"]
