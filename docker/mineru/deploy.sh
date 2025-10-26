#!/bin/bash
# MinerU Container Build & Deploy Script

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
IMAGE_NAME="ledgerleap-mineru"
IMAGE_TAG="cpu"
CONTAINER_NAME="ledgerleap_vlm"
PORT="8001"

echo "=== MinerU Container Build & Deploy ==="
echo "Image: ${IMAGE_NAME}:${IMAGE_TAG}"
echo "Container: ${CONTAINER_NAME}"
echo "Port: ${PORT}"
echo ""

# Stop existing container
if docker ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "Stopping existing container..."
    docker stop "${CONTAINER_NAME}" || true
    docker rm "${CONTAINER_NAME}" || true
fi

# Build image
echo "Building Docker image..."
cd "${SCRIPT_DIR}"
docker build -t "${IMAGE_NAME}:${IMAGE_TAG}" .

# Run container
echo "Starting container..."
docker run -d \
    --name "${CONTAINER_NAME}" \
    -p "${PORT}:8000" \
    --restart unless-stopped \
    "${IMAGE_NAME}:${IMAGE_TAG}"

# Wait for container to be ready
echo "Waiting for container to be ready..."
sleep 5

# Health check
echo "Health check..."
if curl -sf "http://localhost:${PORT}/health" > /dev/null; then
    echo "✅ Container is healthy!"
    curl -s "http://localhost:${PORT}/health" | jq .
else
    echo "❌ Container health check failed"
    echo "Logs:"
    docker logs "${CONTAINER_NAME}" --tail 50
    exit 1
fi

# Verify package versions
echo ""
echo "Package versions:"
docker exec "${CONTAINER_NAME}" pip list | grep -E "(transformers|opencv|numpy|torch|magic-pdf)"

echo ""
echo "=== Deployment Complete ==="
echo "Health endpoint: http://localhost:${PORT}/health"
echo "Extract endpoint: http://localhost:${PORT}/extract/structured"
echo ""
echo "Test command:"
echo "curl -X POST -F 'file=@test.pdf' http://localhost:${PORT}/extract/structured"
