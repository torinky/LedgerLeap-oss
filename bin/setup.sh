#!/bin/bash
#
# LedgerLeap Project Setup Script
#
# This script automates the initial setup of the development environment.
# It builds the Docker containers, installs dependencies, and runs migrations.
#
# Usage:
#   ./bin/setup.sh [-p] [-h]
#
# Options:
#   -p  Use production configuration
#   -h  Show help message
#
# Exit immediately if a command exits with a non-zero status.
set -e

# --- Helper Functions ---
info() {
    echo "INFO: $1"
}

error() {
    echo "ERROR: $1" >&2
}

# macOS (BSD sed) requires empty backup extension; GNU sed (Linux) does not
if [[ "$(uname)" == "Darwin" ]]; then
    sed_i() { sed -i '' "$@"; }
else
    sed_i() { sed -i "$@"; }
fi

print_usage() {
    echo "Usage: $0 [-p] [-n] [-h]"
    echo ""
    echo "Options:"
    echo "  -p  Use production configuration"
    echo "  -n  Build Docker images without cache"
    echo "  -h  Show this help message"
    echo ""
    echo "Environment detection:"
    echo "  - Architecture: Automatically detected (ARM64/AMD64)"
    echo "  - GPU:          Automatically detected (NVIDIA GPU → paddleocr-vl)"
    echo "  - Mac MLX:      Apple Silicon → MLX-VLM (Metal-accelerated)"
    echo ""
    echo "Examples:"
    echo "  $0      # Development environment"
    echo "  $0 -p   # Production environment"
    echo "  $0 -n   # Rebuild without cache"
}

# --- Environment Configuration ---
ENV="development"
NO_CACHE=false
COMPOSE_FILES_ARRAY=()
MLX_ENABLED=false  # Track whether MLX-VLM backend is active

# 0. .env ファイルの存在確認
if [ ! -f .env ]; then
    info "Creating .env file from .env.example..."
    cp .env.example .env
fi

# .env を読み込む（GPU判定等で使用）
if [ -f ".env" ]; then
    set -a
    source .env
    set +a
fi

# 1. ベースファイルの追加
COMPOSE_FILES_ARRAY+=("docker-compose.yml")

# 2. 環境に応じたオーバーライドファイルの判定
while getopts "pnh" opt; do
  case ${opt} in
    p )
      ENV="production"
      if [ ! -f "docker-compose.prod.yml" ]; then
        error "docker-compose.prod.yml not found. This file is excluded from the public repository. Obtain it from the private repository or skip production mode."
        exit 1
      fi
      COMPOSE_FILES_ARRAY+=("docker-compose.prod.yml")
      ;;
    n )
      NO_CACHE=true
      ;;
    h )
      print_usage
      exit 0
      ;;
    \? )
      error "Invalid option"
      print_usage
      exit 1
      ;;
  esac
done

# 開発環境では docker-compose.override.yml が自動で読み込まれる
# （Docker Compose のデフォルト挙動）
if [ "$ENV" = "development" ] && [ -f "docker-compose.override.yml" ]; then
    COMPOSE_FILES_ARRAY+=("docker-compose.override.yml")
    info "Added docker-compose.override.yml to COMPOSE_FILE (development mode)"
    # Docker Composeのデフォルトの自動読み込みは、COMPOSE_FILEが明示的に指定された場合は機能しないため、ここで明示的に追加する
fi

# 3. アーキテクチャの自動検出
ARCH=$(uname -m)
info "Detected architecture: $ARCH"

if [[ "$ARCH" == "arm64" || "$ARCH" == "aarch64" ]]; then
    if [ -f "docker-compose.arm64.yml" ]; then
        COMPOSE_FILES_ARRAY+=("docker-compose.arm64.yml")
        info "Using ARM64 architecture configuration"
    fi
elif [[ "$ARCH" == "x86_64" ]]; then
    if [ -f "docker-compose.amd64.yml" ]; then
        COMPOSE_FILES_ARRAY+=("docker-compose.amd64.yml")
        info "Using AMD64 architecture configuration"
    fi
else
    error "Unsupported architecture: $ARCH"
    exit 1
fi

# 4. GPU利用の自動判定
#    PADDLEOCR_DEVICE=auto ならホスト上のGPUを検出して自動設定する
detect_gpu() {
    # NVIDIA GPU: check nvidia-smi
    if command -v nvidia-smi &>/dev/null && nvidia-smi &>/dev/null 2>&1; then
        return 0
    fi
    # Fallback: check /proc/driver/nvidia (Linux)
    if [[ -c /dev/nvidia0 ]] 2>/dev/null; then
        return 0
    fi
    return 1
}

GPU_ENABLED=false
PADDLEOCR_DEVICE_CURRENT="${PADDLEOCR_DEVICE:-auto}"

if [[ "$PADDLEOCR_DEVICE_CURRENT" == "auto" ]]; then
    if detect_gpu; then
        info "NVIDIA GPU detected on host — setting PADDLEOCR_DEVICE=gpu"
        sed_i "s|^PADDLEOCR_DEVICE=.*|PADDLEOCR_DEVICE=gpu|" .env 2>/dev/null || echo "PADDLEOCR_DEVICE=gpu" >> .env
        export PADDLEOCR_DEVICE=gpu
        PADDLEOCR_DEVICE_CURRENT=gpu
    else
        info "No NVIDIA GPU detected — using CPU mode"
        sed_i "s|^PADDLEOCR_DEVICE=.*|PADDLEOCR_DEVICE=cpu|" .env 2>/dev/null || echo "PADDLEOCR_DEVICE=cpu" >> .env
        export PADDLEOCR_DEVICE=cpu
        PADDLEOCR_DEVICE_CURRENT=cpu
    fi
fi

if [ "$PADDLEOCR_DEVICE_CURRENT" = "gpu" ]; then
    if [ -f "docker-compose.gpu.yml" ]; then
        COMPOSE_FILES_ARRAY+=("docker-compose.gpu.yml")
        GPU_ENABLED=true
        info "GPU support enabled"
    else
        error "docker-compose.gpu.yml not found, but PADDLEOCR_DEVICE=gpu is set"
        exit 1
    fi
fi

# 5. x86/AMD64 VLM バックエンド自動選択
#    GPU → paddleocr-vl (最良品質), CPU → paddleocr-vl-cpu (CPU向けVLモデル)
if [[ "$ARCH" == "x86_64" ]]; then
    VLM_MODEL_CURRENT="${VLM_MODEL:-}"
    # 自動設定値（paddleocr / paddleocr-vl / paddleocr-vl-cpu）なら再評価する
    # ユーザーが marker/mineru 等の別の値を設定している場合はスキップ
    case "$VLM_MODEL_CURRENT" in
        ""|paddleocr|paddleocr-vl|paddleocr-vl-cpu)
            if [[ "$GPU_ENABLED" == "true" ]]; then
                info "x86 + GPU — selecting paddleocr-vl (GPU-accelerated Vision-Language model)"
                sed_i "s|^VLM_MODEL=.*|VLM_MODEL=paddleocr-vl|" .env 2>/dev/null || echo "VLM_MODEL=paddleocr-vl" >> .env
                export VLM_MODEL=paddleocr-vl
            else
                info "x86 + CPU — selecting paddleocr-vl-cpu (CPU-optimized Vision-Language model)"
                sed_i "s|^VLM_MODEL=.*|VLM_MODEL=paddleocr-vl-cpu|" .env 2>/dev/null || echo "VLM_MODEL=paddleocr-vl-cpu" >> .env
                export VLM_MODEL=paddleocr-vl-cpu
            fi
            ;;
        *)
            info "VLM_MODEL is set to '$VLM_MODEL_CURRENT' — skipping auto-detection"
            ;;
    esac
fi

# 6. Mac Apple Silicon VLM バックエンド自動選択
#    MLX-VLM を Mac ホスト上で直接実行し、Docker の vlm コンテナを迂回する
#    mlx-vlm のインストールに失敗した場合は Docker vlm にフォールバックする
if [[ "$(uname)" == "Darwin" && "$(uname -m)" == "arm64" ]]; then
    VLM_MODEL_CURRENT="${VLM_MODEL:-}"
    VLM_URL_CURRENT="${VLM_URL:-http://vlm:8000}"

    # 自動設定値（paddleocr / paddleocr-vl-mlx / auto）なら再評価する
    # ユーザーが marker/mineru 等の別の値を設定している場合はスキップ
    case "$VLM_MODEL_CURRENT" in
        ""|paddleocr|paddleocr-vl-mlx|auto)
            info "Mac Apple Silicon detected — checking MLX-VLM availability..."

            MLX_READY=false
            MIN_MLX_VERSION="0.6.0"

            # Step 1: install mlx-vlm via the setup script
            if [ -f "./scripts/start-vlm-mlx.sh" ]; then
                if bash ./scripts/start-vlm-mlx.sh --install; then
                    # Step 2: verify installed version from venv
                    if [ -f ".venv-mlx/bin/python" ]; then
                        MLX_VER=$(.venv-mlx/bin/python -c "import mlx_vlm; print(mlx_vlm.__version__)" 2>/dev/null) || MLX_VER=""
                    else
                        MLX_VER=""
                    fi

                    if [[ -n "$MLX_VER" ]]; then
                        # Semver comparison: check $MLX_VER >= $MIN_MLX_VERSION
                        VER_CHECK=$(printf '%s\n%s\n' "$MIN_MLX_VERSION" "$MLX_VER" | sort -V | tail -1)
                        if [[ "$VER_CHECK" == "$MLX_VER" ]]; then
                            info "mlx-vlm $MLX_VER installed (>= $MIN_MLX_VERSION required) — OK"
                            MLX_READY=true
                        else
                            info "mlx-vlm $MLX_VER is too old (need >= $MIN_MLX_VERSION)"
                        fi
                    else
                        info "mlx-vlm installation could not be verified"
                    fi
                else
                    info "mlx-vlm installation failed or was skipped"
                fi
            else
                info "scripts/start-vlm-mlx.sh not found — skipping MLX setup"
            fi

            # Step 3: configure .env based on availability
            if [[ "$MLX_READY" == "true" ]]; then
                info "MLX-VLM is ready — enabling Mac-native MLX backend"

                sed_i "s|^VLM_MODEL=.*|VLM_MODEL=auto|" .env 2>/dev/null || echo "VLM_MODEL=auto" >> .env
                sed_i "s|^VLM_URL=.*|VLM_URL=http://host.docker.internal:8000|" .env 2>/dev/null || echo "VLM_URL=http://host.docker.internal:8000" >> .env

                export VLM_MODEL=auto
                export VLM_URL=http://host.docker.internal:8000
                MLX_ENABLED=true

                info "VLM_MODEL=auto (auto-detect → paddleocr-vl-mlx on Mac)"
                info "VLM_URL=http://host.docker.internal:8000 (Mac host MLX server)"
            else
                warn() { echo "WARN: $1"; }
                warn "MLX-VLM not available — falling back to Docker vlm service"
                warn "To enable MLX later, run: ./scripts/start-vlm-mlx.sh --install"

                # Keep existing Docker-based settings (VLM_MODEL=paddleocr, VLM_URL=http://vlm:8000)
            fi
            ;;
        *)
            info "VLM_MODEL is set to '$VLM_MODEL_CURRENT' — skipping auto-detection"
            ;;
    esac
fi

# 7. COMPOSE_FILE環境変数を構築
export COMPOSE_FILE=$(IFS=: ; echo "${COMPOSE_FILES_ARRAY[*]}")
info "Using COMPOSE_FILE: $COMPOSE_FILE"

# --- Prerequisites Check ---

# vendor/ がない場合は Docker 経由で Composer をブートストラップする
# (vendor/ は .gitignore 対象のため fresh clone では存在しない)
if [ ! -f "./vendor/bin/sail" ]; then
    info "vendor/ not found. Bootstrapping Composer dependencies via Docker..."
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed or not in PATH. Please install Docker and try again."
        exit 1
    fi
    docker run --rm \
        -v "$(pwd):/app" \
        -w /app \
        composer:latest install --ignore-platform-reqs --no-scripts
    info "Composer bootstrap complete."
fi

# --- Main Setup ---

info "Starting LedgerLeap setup..."

# Build and start Docker containers
info "Building and starting Docker containers with Sail... (This may take a while)"

BUILD_ARGS=""
if [ "$NO_CACHE" = true ]; then
    info "Building without cache..."
    BUILD_ARGS="--no-cache"
fi

./vendor/bin/sail build $BUILD_ARGS
./vendor/bin/sail up -d

# Install dependencies and run migrations
info "Installing dependencies and running migrations..."

# Clean Node.js modules and lock file on the host
info "Cleaning Node.js modules and lock file on the host..."
rm -rf node_modules package-lock.json

./bin/install_dependencies_and_migrate.sh

info "Setup complete! The application should be running at http://localhost"
echo "You can now create a tenant using 'sail artisan tinker'."

# Mac Apple Silicon の場合、MLX-VLM サーバーの起動手順を表示
if [[ "$MLX_ENABLED" == "true" ]]; then
    echo ""
    echo "=========================================="
    echo "  Mac Apple Silicon VLM Setup"
    echo "=========================================="
    echo ""
    echo "MLX-VLM is configured for OCR on this Mac."
    echo "Start the MLX-VLM server in a separate terminal:"
    echo ""
    echo "  ./scripts/start-vlm-mlx.sh"
    echo ""
    echo "First run downloads ~2 GB model (one-time)."
    echo "Health check: http://localhost:8000/health"
    echo "API docs:     http://localhost:8000/docs"
    echo ""
    echo "The Docker vlm service is still running but not used"
    echo "(VLM_URL points to the Mac host)."
    echo "=========================================="
fi
