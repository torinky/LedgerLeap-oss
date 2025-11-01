#!/bin/bash
# PaddleOCR Version Switcher
# Switch between PaddleOCR 2.x (stable), 3.x (experimental) and paddleocr-vl (gpu)

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
PADDLE_DIR="$PROJECT_ROOT/docker/paddle"
ENV_FILE="$PROJECT_ROOT/.env"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_usage() {
    echo "Usage: $0 [version]"
    echo ""
    echo "Versions:"
    echo "  2    - PaddleOCR 2.8.1 (stable, recommended)"
    echo "  3    - PaddleOCR 3.3+ (experimental, has SIGSEGV issues on ARM64)"
    echo "  gpu  - PaddleOCR-VL (GPU required)"
    echo ""
    echo "Examples:"
    echo "  $0 2     # Switch to version 2.x"
    echo "  $0 3     # Switch to version 3.x"
    echo "  $0 gpu   # Switch to GPU version"
    echo ""
}

print_current_version() {
    if [ -f "$PADDLE_DIR/app.py" ]; then
        if grep -q "# PaddleOCR 2.x compatible version" "$PADDLE_DIR/app.py"; then
            echo -e "${GREEN}Current version: 2.x (stable)${NC}"
        elif grep -q "# PaddleOCR 3.x compatible version" "$PADDLE_DIR/app.py"; then
            echo -e "${YELLOW}Current version: 3.x (experimental)${NC}"
        else
            echo -e "${BLUE}Current version: Unknown${NC}"
        fi
    else
        echo -e "${RED}app.py not found${NC}"
    fi

    if grep -q "VLM_MODEL=paddleocr-vl" "$ENV_FILE"; then
        echo -e "${BLUE}Current VLM_MODEL: paddleocr-vl (GPU)${NC}"
    else
        echo -e "${GREEN}Current VLM_MODEL: paddleocr (CPU)${NC}"
    fi
}

update_env_file() {
    local key=$1
    local value=$2

    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
}

remove_from_env_file() {
    local key=$1
    sed -i "/^${key}=/d" "$ENV_FILE"
}

switch_to_v2() {
    echo -e "${GREEN}Switching to PaddleOCR 2.x (stable)...${NC}"
    
    # Backup current files
    if [ -f "$PADDLE_DIR/app.py" ]; then
        cp "$PADDLE_DIR/app.py" "$PADDLE_DIR/app.py.backup"
    fi
    if [ -f "$PADDLE_DIR/requirements.txt" ]; then
        cp "$PADDLE_DIR/requirements.txt" "$PADDLE_DIR/requirements.txt.backup"
    fi
    
    # Copy version 2 files
    cp "$PADDLE_DIR/app.py.v2" "$PADDLE_DIR/app.py"
    cp "$PADDLE_DIR/requirements.txt.v2" "$PADDLE_DIR/requirements.txt"
    
    # Update .env file
    update_env_file "VLM_MODEL" "paddleocr"
    update_env_file "PADDLEOCR_DEVICE" "cpu"
    remove_from_env_file "COMPOSE_FILE"

    echo -e "${GREEN}✅ Switched to PaddleOCR 2.x${NC}"
    echo ""
    echo "Changes:"
    echo "  - PaddleOCR: 2.8.1 (PP-OCRv5 model)"
    echo "  - PaddlePaddle: 2.6.2"
    echo "  - API: 2.x compatible (use_angle_cls, use_gpu, .ocr())"
    echo "  - VLM_MODEL set to 'paddleocr'"
    echo "  - PADDLEOCR_DEVICE set to 'cpu'"
    echo "  - COMPOSE_FILE removed from .env"
    echo ""
    echo "Next steps:"
    echo "  1. Rebuild container: ./vendor/bin/sail build --no-cache vlm"
    echo "  2. Restart service:   ./vendor/bin/sail up -d vlm"
    echo "  3. Run tests:         ./vendor/bin/sail test --filter=PaddleOcrVlmTest"
}

switch_to_v3() {
    echo -e "${YELLOW}⚠️  WARNING: Switching to PaddleOCR 3.x (experimental)${NC}"
    echo ""
    echo "Known issues:"
    echo "  - SIGSEGV (Segmentation Fault) on ARM64/Apple Silicon"
    echo "  - Initialization succeeds, but OCR execution crashes"
    echo "  - Not recommended for production use"
    echo ""
    read -p "Do you want to continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Cancelled."
        exit 0
    fi
    
    echo -e "${YELLOW}Switching to PaddleOCR 3.x (experimental)...${NC}"
    
    # Backup current files
    if [ -f "$PADDLE_DIR/app.py" ]; then
        cp "$PADDLE_DIR/app.py" "$PADDLE_DIR/app.py.backup"
    fi
    if [ -f "$PADDLE_DIR/requirements.txt" ]; then
        cp "$PADDLE_DIR/requirements.txt" "$PADDLE_DIR/requirements.txt.backup"
    fi
    
    # Copy version 3 files
    cp "$PADDLE_DIR/app.py.v3" "$PADDLE_DIR/app.py"
    cp "$PADDLE_DIR/requirements.txt.v3" "$PADDLE_DIR/requirements.txt"

    # Update .env file
    update_env_file "VLM_MODEL" "paddleocr"
    update_env_file "PADDLEOCR_DEVICE" "cpu"
    remove_from_env_file "COMPOSE_FILE"

    echo -e "${YELLOW}✅ Switched to PaddleOCR 3.x${NC}"
    echo ""
    echo "Changes:"
    echo "  - PaddleOCR: 3.3+"
    echo "  - PaddlePaddle: 3.0+"
    echo "  - API: 3.x compatible (lang only, minimal config)"
    echo "  - VLM_MODEL set to 'paddleocr'"
    echo "  - PADDLEOCR_DEVICE set to 'cpu'"
    echo "  - COMPOSE_FILE removed from .env"
    echo ""
    echo "Next steps:"
    echo "  1. Rebuild container: ./vendor/bin/sail build --no-cache vlm"
    echo "  2. Restart service:   ./vendor/bin/sail up -d vlm"
    echo "  3. Monitor logs:      docker logs -f ledgerleap_vlm"
    echo ""
    echo -e "${RED}⚠️  May crash with SIGSEGV during OCR execution${NC}"
}

switch_to_gpu() {
    echo -e "${BLUE}Switching to PaddleOCR-VL (GPU)...${NC}"

    # Update .env file
    update_env_file "VLM_MODEL" "paddleocr-vl"
    update_env_file "PADDLEOCR_DEVICE" "gpu"
    update_env_file "COMPOSE_FILE" "docker-compose.yml:docker-compose.gpu.yml"

    echo -e "${BLUE}✅ Switched to PaddleOCR-VL (GPU)${NC}"
    echo ""
    echo "Changes:"
    echo "  - VLM_MODEL set to 'paddleocr-vl'"
    echo "  - PADDLEOCR_DEVICE set to 'gpu'"
    echo "  - COMPOSE_FILE set to 'docker-compose.yml:docker-compose.gpu.yml'"
    echo ""
    echo "Next steps:"
    echo "  1. Ensure you have a compatible NVIDIA GPU and nvidia-docker installed."
    echo "  2. Rebuild container: ./vendor/bin/sail build --no-cache vlm"
    echo "  3. Restart service:   ./vendor/bin/sail up -d vlm"
    echo "  4. Monitor logs:      docker logs -f ledgerleap_vlm"
}

# Main
echo "═══════════════════════════════════════"
echo "  PaddleOCR Version Switcher"
echo "═══════════════════════════════════════"
echo ""

print_current_version
echo ""

if [ $# -eq 0 ]; then
    print_usage
    exit 1
fi

case "$1" in
    2)
        switch_to_v2
        ;;
    3)
        switch_to_v3
        ;;
    gpu)
        switch_to_gpu
        ;;
    *)
        echo -e "${RED}Error: Invalid version '$1'${NC}"
        echo ""
        print_usage
        exit 1
        ;;
esac
