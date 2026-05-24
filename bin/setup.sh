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
    echo "  - GPU support: Based on PADDLEOCR_DEVICE in .env"
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

# 4. GPU利用の判定
if [ "$PADDLEOCR_DEVICE" = "gpu" ]; then
    if [ -f "docker-compose.gpu.yml" ]; then
        COMPOSE_FILES_ARRAY+=("docker-compose.gpu.yml")
        info "GPU support enabled"
    else
        error "docker-compose.gpu.yml not found, but PADDLEOCR_DEVICE=gpu is set"
        exit 1
    fi
fi

# 5. COMPOSE_FILE環境変数を構築
export COMPOSE_FILE=$(IFS=: ; echo "${COMPOSE_FILES_ARRAY[*]}")
info "Using COMPOSE_FILE: $COMPOSE_FILE"

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
