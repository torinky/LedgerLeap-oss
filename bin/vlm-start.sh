#!/bin/bash
# VLM Service Startup Script
# Automatically selects the correct Docker context based on VLM_MODEL environment variable

set -e

# Load environment variables from .env file
if [ -f .env ]; then
    source .env
fi

# Default to paddleocr if not set
VLM_MODEL=${VLM_MODEL:-paddleocr}

# Determine the Docker context based on VLM_MODEL
case "$VLM_MODEL" in
    paddleocr)
        export VLM_SERVICE_CONTEXT="./docker/paddle"
        export VLM_INTERNAL_PORT=8000
        echo "🔧 Using PaddleOCR 2.7.3 (stable version)"
        ;;
    paddleocr-vl)
        export VLM_SERVICE_CONTEXT="./docker/paddleocr-vl"
        export VLM_INTERNAL_PORT=8002
        echo "🚀 Using PaddleOCR-VL 0.9B (experimental version)"
        ;;
    marker)
        export VLM_SERVICE_CONTEXT="./docker/marker"
        export VLM_INTERNAL_PORT=8000
        echo "📄 Using Marker (PDF to Markdown converter)"
        ;;
    *)
        echo "❌ Error: Unknown VLM_MODEL value: $VLM_MODEL"
        echo "   Valid values: paddleocr, paddleocr-vl, marker"
        exit 1
        ;;
esac

echo "   Context: $VLM_SERVICE_CONTEXT"
echo "   Port: ${VLM_SERVICE_PORT:-8001} -> $VLM_INTERNAL_PORT"
echo ""

# Execute the provided command
exec "$@"
