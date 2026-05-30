# docker/mineru/Dockerfile
# CPU-only MinerU service for LedgerLeap
# Based on official MinerU Dockerfile but optimized for CPU
FROM python:3.10-slim

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    fonts-noto-core \
    fonts-noto-cjk \
    fontconfig \
    curl \
    && fc-cache -fv \
    && rm -rf /var/lib/apt/lists/*

# Install PyTorch CPU version first
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir torch torchvision --index-url https://download.pytorch.org/whl/cpu

# Install MinerU with core dependencies
RUN pip install --no-cache-dir 'mineru[core]'

# Install FastAPI for API service
RUN pip install --no-cache-dir \
    fastapi==0.104.1 \
    uvicorn[standard]==0.24.0 \
    python-multipart==0.0.6

# Download models using the correct command
# MinerU v2.6+ uses magic-pdf-models-download
RUN echo "=== Downloading MinerU models ===" && \
    magic-pdf-models-download || \
    (echo "Trying alternative command..." && \
     mineru-models-download -s huggingface -m all) || \
    echo "WARNING: Model download failed"

# Verify models were downloaded
RUN echo "=== Verifying models ===" && \
    ls -lah /root/.cache/huggingface/hub/ 2>/dev/null || \
    echo "No HuggingFace cache found" && \
    find /root -name "*.pt" -o -name "*.onnx" 2>/dev/null | head -10 || \
    echo "No model files found"

# Copy configuration file to home directory (MinerU expects it here)
COPY magic-pdf.json /root/magic-pdf.json

# Verify configuration
RUN echo "=== Configuration ===" && \
    cat /root/magic-pdf.json && \
    echo ""

# Unified API will be available via volume mount at /var/www/html/docker/paddle/unified_api.py

# Create models directory
RUN mkdir -p /app/models

# Set environment variables for CPU optimization
ENV MINERU_MODEL_SOURCE=local \
    OMP_NUM_THREADS=4 \
    MKL_NUM_THREADS=4 \
    DEVICE_MODE=cpu \
    CUDA_VISIBLE_DEVICES="" \
    TORCH_DEVICE=cpu

# Expose port
EXPOSE 8000

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8000/health || exit 1

# Run FastAPI application with unified API
# In production, this will be available via volume mount at /var/www/html
WORKDIR /var/www/html
CMD ["uvicorn", "docker.paddle.unified_api:app", "--host", "0.0.0.0", "--port", "8000"]
