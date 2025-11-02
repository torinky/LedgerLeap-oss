#!/bin/bash
# VLM Model Switcher
# Easily switch between PaddleOCR models

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"

# Color codes
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

PURPLE='\033[0;35m'

show_usage() {
    echo "Usage: $0 [paddleocr|paddleocr-vl|marker|mineru|status]"
    echo ""
    echo "Commands:"
    echo "  paddleocr      Switch to PaddleOCR 2.7.3 (stable)"
    echo "  paddleocr-vl   Switch to PaddleOCR-VL 0.9B (experimental)"
    echo "  marker         Switch to Marker (PDF to Markdown)"
    echo "  mineru         Switch to MinerU (PDF to Markdown)"
    echo "  status         Show current VLM model configuration"
    echo ""
    echo "Examples:"
    echo "  $0 paddleocr"
    echo "  $0 paddleocr-vl"
    echo "  $0 marker"
    echo "  $0 mineru"
    echo "  $0 status"
}

get_current_model() {
    if [ -f "$ENV_FILE" ]; then
        grep "^VLM_MODEL=" "$ENV_FILE" | cut -d'=' -f2 || echo "paddleocr"
    else
        echo "paddleocr"
    fi
}

show_status() {
    local current_model=$(get_current_model)
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "  VLM Model Configuration Status"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    if [ "$current_model" = "paddleocr" ]; then
        echo -e "  Current Model: ${GREEN}PaddleOCR 2.7.3${NC} (stable)"
        echo "  Context: ./docker/paddle"
        echo "  Port: 8001 -> 8000"
        echo "  Status: ✅ Production-ready"
    elif [ "$current_model" = "paddleocr-vl" ]; then
        echo -e "  Current Model: ${BLUE}PaddleOCR-VL 0.9B${NC} (experimental)"
        echo "  Context: ./docker/paddleocr-vl"
        echo "  Port: 8001 -> 8002"
        echo "  Status: 🧪 Experimental"
    elif [ "$current_model" = "marker" ]; then
        echo -e "  Current Model: ${YELLOW}Marker${NC} (PDF to Markdown)"
        echo "  Context: ./docker/marker"
        echo "  Port: 8001 -> 8000"
        echo "  Status: 📄 PDF Specialized"
    elif [ "$current_model" = "mineru" ]; then
        echo -e "  Current Model: ${PURPLE}MinerU${NC} (PDF to Markdown)"
        echo "  Context: ./docker/mineru"
        echo "  Port: 8001 -> 8000"
        echo "  Status: 📄 PDF Specialized"
    else
        echo -e "  Current Model: ${RED}Unknown ($current_model)${NC}"
    fi
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

switch_model() {
    local target_model="$1"
    
    case "$target_model" in
        paddleocr)
            echo -e "${GREEN}Switching to PaddleOCR 2.7.3 (stable)${NC}"
            
            if [ -f "$ENV_FILE" ]; then
                # Update existing .env
                sed -i.bak 's/^VLM_MODEL=.*/VLM_MODEL=paddleocr/' "$ENV_FILE"
                sed -i.bak 's/^VLM_SERVICE_CONTEXT=.*/VLM_SERVICE_CONTEXT=.\/docker\/paddle/' "$ENV_FILE"
                sed -i.bak 's/^VLM_INTERNAL_PORT=.*/VLM_INTERNAL_PORT=8000/' "$ENV_FILE"
                sed -i.bak 's/^PADDLEOCR_DEVICE=.*/PADDLEOCR_DEVICE=cpu/' "$ENV_FILE" 2>/dev/null || echo "PADDLEOCR_DEVICE=cpu" >> "$ENV_FILE"
                rm -f "$ENV_FILE.bak"
            else
                echo -e "${RED}Error: .env file not found${NC}"
                echo "Please copy .env.example to .env first"
                exit 1
            fi
            
            echo ""
            echo "✅ Configuration updated"
            echo "   VLM_MODEL=paddleocr"
            echo "   VLM_SERVICE_CONTEXT=./docker/paddle"
            echo "   VLM_INTERNAL_PORT=8000"
            echo "   PADDLEOCR_DEVICE=cpu"
            echo ""
            echo "Next steps:"
            echo "  1. docker-compose down vlm"
            echo "  2. docker-compose build vlm"
            echo "  3. docker-compose up -d vlm"
            ;;
            
        paddleocr-vl)
            echo -e "${BLUE}Switching to PaddleOCR-VL 0.9B (experimental)${NC}"
            echo -e "${YELLOW}⚠️  Warning: This is an experimental feature${NC}"
            
            if [ -f "$ENV_FILE" ]; then
                # Update existing .env
                sed -i.bak 's/^VLM_MODEL=.*/VLM_MODEL=paddleocr-vl/' "$ENV_FILE"
                sed -i.bak 's/^VLM_SERVICE_CONTEXT=.*/VLM_SERVICE_CONTEXT=.\/docker\/paddleocr-vl/' "$ENV_FILE"
                sed -i.bak 's/^VLM_INTERNAL_PORT=.*/VLM_INTERNAL_PORT=8002/' "$ENV_FILE"
                sed -i.bak 's/^PADDLEOCR_DEVICE=.*/PADDLEOCR_DEVICE=gpu/' "$ENV_FILE" 2>/dev/null || echo "PADDLEOCR_DEVICE=gpu" >> "$ENV_FILE"
                rm -f "$ENV_FILE.bak"
            else
                echo -e "${RED}Error: .env file not found${NC}"
                echo "Please copy .env.example to .env first"
                exit 1
            fi
            
            echo ""
            echo "✅ Configuration updated"
            echo "   VLM_MODEL=paddleocr-vl"
            echo "   VLM_SERVICE_CONTEXT=./docker/paddleocr-vl"
            echo "   VLM_INTERNAL_PORT=8002"
            echo "   PADDLEOCR_DEVICE=gpu (GPU required)"
            echo ""
            echo "Next steps:"
            echo "  1. docker-compose down vlm"
            echo "  2. docker-compose build vlm --no-cache"
            echo "  3. docker-compose up -d vlm"
            echo "  4. Check health: curl http://localhost:8001/health"
            ;;
            
        marker)
            echo -e "${YELLOW}Switching to Marker (PDF to Markdown)${NC}"
            
            if [ -f "$ENV_FILE" ]; then
                # Update existing .env
                sed -i.bak 's/^VLM_MODEL=.*/VLM_MODEL=marker/' "$ENV_FILE"
                sed -i.bak 's/^VLM_SERVICE_CONTEXT=.*/VLM_SERVICE_CONTEXT=.\/docker\/marker/' "$ENV_FILE"
                sed -i.bak 's/^VLM_INTERNAL_PORT=.*/VLM_INTERNAL_PORT=8000/' "$ENV_FILE"
                sed -i.bak 's/^PADDLEOCR_DEVICE=.*/PADDLEOCR_DEVICE=cpu/' "$ENV_FILE" 2>/dev/null || echo "PADDLEOCR_DEVICE=cpu" >> "$ENV_FILE"
                rm -f "$ENV_FILE.bak"
            else
                echo -e "${RED}Error: .env file not found${NC}"
                echo "Please copy .env.example to .env first"
                exit 1
            fi
            
            echo ""
            echo "✅ Configuration updated"
            echo "   VLM_MODEL=marker"
            echo "   VLM_SERVICE_CONTEXT=./docker/marker"
            echo "   VLM_INTERNAL_PORT=8000"
            echo "   PADDLEOCR_DEVICE=cpu"
            echo ""
            echo "Next steps:"
            echo "  1. docker-compose down vlm"
            echo "  2. docker-compose build vlm --no-cache"
            echo "  3. docker-compose up -d vlm"
            echo "  4. Check health: curl http://localhost:8001/health"
            ;;

        mineru)
            echo -e "${PURPLE}Switching to MinerU (PDF to Markdown)${NC}"
            
            if [ -f "$ENV_FILE" ]; then
                # Update existing .env
                sed -i.bak 's/^VLM_MODEL=.*/VLM_MODEL=mineru/' "$ENV_FILE"
                sed -i.bak 's/^VLM_SERVICE_CONTEXT=.*/VLM_SERVICE_CONTEXT=.\/docker\/mineru/' "$ENV_FILE"
                sed -i.bak 's/^VLM_INTERNAL_PORT=.*/VLM_INTERNAL_PORT=8000/' "$ENV_FILE"
                sed -i.bak 's/^PADDLEOCR_DEVICE=.*/PADDLEOCR_DEVICE=cpu/' "$ENV_FILE" 2>/dev/null || echo "PADDLEOCR_DEVICE=cpu" >> "$ENV_FILE"
                rm -f "$ENV_FILE.bak"
            else
                echo -e "${RED}Error: .env file not found${NC}"
                echo "Please copy .env.example to .env first"
                exit 1
            fi
            
            echo ""
            echo "✅ Configuration updated"
            echo "   VLM_MODEL=mineru"
            echo "   VLM_SERVICE_CONTEXT=./docker/mineru"
            echo "   VLM_INTERNAL_PORT=8000"
            echo "   PADDLEOCR_DEVICE=cpu"
            echo ""
            echo "Next steps:"
            echo "  1. docker-compose down vlm"
            echo "  2. docker-compose build vlm --no-cache"
            echo "  3. docker-compose up -d vlm"
            echo "  4. Check health: curl http://localhost:8001/health"
            ;;
            
        *)
            echo -e "${RED}Error: Unknown model '$target_model'${NC}"
            show_usage
            exit 1
            ;;
    esac
}

# Main
case "${1:-}" in
    paddleocr|paddleocr-vl|marker|mineru)
        switch_model "$1"
        echo ""
        show_status
        ;;
    status)
        show_status
        ;;
    *)
        show_usage
        exit 1
        ;;
esac
