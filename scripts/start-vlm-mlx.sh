#!/usr/bin/env bash
# start-vlm-mlx.sh — Mac M-series VLM server launcher
#
# Detects Apple Silicon, checks mlx-vlm version compatibility, installs
# missing dependencies, and starts unified_api.py with paddleocr-vl-mlx backend.
#
# Usage:
#   ./scripts/start-vlm-mlx.sh              # start server (port 8000)
#   ./scripts/start-vlm-mlx.sh --install    # install deps only
#   ./scripts/start-vlm-mlx.sh --check      # check environment only
#   VLM_PORT=8001 ./scripts/start-vlm-mlx.sh  # custom port
#
# Requires: Python >= 3.10, pip
# Connects: Laravel in Sail reaches this via VLM_URL=http://host.docker.internal:8000

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

# ---- Min versions ----
MIN_PYTHON_MAJOR=3
MIN_PYTHON_MINOR=10
MIN_MLX_VLM_VERSION="0.6.0"

# ---- Colors ----
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

check_mark() { echo -e "${GREEN}✓${NC} $1"; }
warn_mark()  { echo -e "${YELLOW}⚠${NC} $1"; }
fail_mark()  { echo -e "${RED}✗${NC} $1"; }

# ---- Detect platform ----
echo ""
echo "=== Platform Detection ==="
if [[ "$(uname)" != "Darwin" ]]; then
    fail_mark "This script is for macOS (Apple Silicon) only."
    echo "  For Linux/x64, use the Docker-based backend: docker compose up -d vlm"
    exit 1
fi

ARCH="$(uname -m)"
if [[ "$ARCH" != "arm64" ]]; then
    warn_mark "Not Apple Silicon (arch: $ARCH). MLX requires M1/M2/M3/M4."
else
    check_mark "Apple Silicon detected ($ARCH)"
fi

# ---- Config ----
PORT="${VLM_PORT:-8000}"
MODEL_PATH="${MLX_MODEL_PATH:-PaddlePaddle/PaddleOCR-VL}"
MODE="${1:-start}"

# ---- Find Python ----
echo ""
echo "=== Python Environment ==="
PYTHON=""
for candidate in python3.12 python3.11 python3.10 python3; do
    if command -v "$candidate" &>/dev/null; then
        ver=$("$candidate" -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')
        major=$("$candidate" -c 'import sys; print(sys.version_info.major)')
        minor=$("$candidate" -c 'import sys; print(sys.version_info.minor)')
        if [[ "$major" -ge "$MIN_PYTHON_MAJOR" && "$minor" -ge "$MIN_PYTHON_MINOR" ]]; then
            PYTHON="$candidate"
            check_mark "Python $ver ($PYTHON)"
            break
        fi
    fi
done

if [[ -z "$PYTHON" ]]; then
    fail_mark "Python >= $MIN_PYTHON_MAJOR.$MIN_PYTHON_MINOR required."
    echo "  Install via: brew install python@3.12"
    exit 1
fi

# ---- Venv setup ----
VENV_DIR="$PROJECT_ROOT/.venv-mlx"
if [[ ! -d "$VENV_DIR" ]]; then
    echo ""
    echo "=== Creating virtual environment: $VENV_DIR ==="
    "$PYTHON" -m venv "$VENV_DIR"
fi
source "$VENV_DIR/bin/activate"
pip install --quiet --upgrade pip

# ---- Check/install mlx ----
echo ""
echo "=== MLX / MLX-VLM ==="

_ver_ge() {
    # Returns 0 if $1 >= $2 (semver comparison)
    printf '%s\n%s\n' "$2" "$1" | sort -V -C
}

MLX_VLM_VER=$("$VENV_DIR/bin/python" -c "import mlx_vlm; print(mlx_vlm.__version__)" 2>/dev/null || echo "")
MLX_VER=$("$VENV_DIR/bin/python" -c "import mlx; print(mlx.__version__)" 2>/dev/null || echo "")

if [[ -n "$MLX_VLM_VER" ]]; then
    if _ver_ge "$MLX_VLM_VER" "$MIN_MLX_VLM_VERSION"; then
        check_mark "mlx-vlm $MLX_VLM_VER (mlx $MLX_VER) — compatible"
    else
        warn_mark "mlx-vlm $MLX_VLM_VER is below minimum $MIN_MLX_VLM_VERSION"
        echo "  Upgrading mlx-vlm..."
        pip install --upgrade "mlx-vlm>=$MIN_MLX_VLM_VERSION"
        MLX_VLM_VER=$("$VENV_DIR/bin/python" -c "import mlx_vlm; print(mlx_vlm.__version__)")
        check_mark "mlx-vlm upgraded to $MLX_VLM_VER"
    fi
else
    warn_mark "mlx-vlm not installed"
    echo "  Installing mlx-vlm (includes mlx)..."
    pip install "mlx-vlm>=$MIN_MLX_VLM_VERSION"
    MLX_VLM_VER=$("$VENV_DIR/bin/python" -c "import mlx_vlm; print(mlx_vlm.__version__)")
    check_mark "mlx-vlm $MLX_VLM_VER installed"
fi

# Install FastAPI + uvicorn (for unified_api.py server)
pip install --quiet fastapi uvicorn python-multipart
check_mark "FastAPI + uvicorn ready"

# ---- Check model cache ----
echo ""
echo "=== Model Cache ==="
HF_CACHE="${HF_HOME:-$HOME/.cache/huggingface}/hub"
if [[ -d "$HF_CACHE" ]]; then
    # Count safetensors files for PaddleOCR-VL
    MODEL_FILES=$(find "$HF_CACHE" -path "*PaddleOCR-VL*" -name "*.safetensors" 2>/dev/null | wc -l | tr -d ' ')
    if [[ "$MODEL_FILES" -gt 0 ]]; then
        check_mark "Model cached (~$MODEL_FILES safetensors files in HuggingFace cache)"
    else
        warn_mark "Model not yet downloaded (will download on first start, ~2 GB)"
    fi
else
    warn_mark "HuggingFace cache not found (will be created on first start)"
fi

# ---- Mode dispatch ----
if [[ "$MODE" == "--check" ]]; then
    echo ""
    echo -e "${GREEN}=== Environment check complete ===${NC}"
    echo ""
    echo "To start the server:"
    echo "  ./scripts/start-vlm-mlx.sh"
    echo ""
    echo "To configure Laravel (.env):"
    echo "  VLM_URL=http://host.docker.internal:$PORT"
    echo "  VLM_MODEL=paddleocr-vl-mlx   # or 'auto' for auto-detection"
    exit 0
fi

if [[ "$MODE" == "--install" ]]; then
    echo ""
    echo -e "${GREEN}=== Installation complete ===${NC}"
    echo ""
    echo "Model will be downloaded on first server start."
    echo "Run: ./scripts/start-vlm-mlx.sh"
    exit 0
fi

# ---- Start server ----
echo ""
echo "=== Starting VLM server (MLX-VLM / PaddleOCR-VL) ==="
echo "Port:       $PORT"
echo "Model:      $MODEL_PATH"
echo "Backend:    paddleocr-vl-mlx"
echo "Health:     http://localhost:$PORT/health"
echo "API docs:   http://localhost:$PORT/docs"
echo ""
echo "NOTE: First run downloads ~2 GB model from HuggingFace."
echo "      Subsequent starts load from local cache (~5s)."
echo ""

cd "$PROJECT_ROOT"

export VLM_MODEL="paddleocr-vl-mlx"
export MLX_MODEL_PATH="$MODEL_PATH"
export PYTHONPATH="$PROJECT_ROOT:$PYTHONPATH"

exec uvicorn docker.paddle.unified_api:app \
    --host 0.0.0.0 \
    --port "$PORT" \
    --log-level info
