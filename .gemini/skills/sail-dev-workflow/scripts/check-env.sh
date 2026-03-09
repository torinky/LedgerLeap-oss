#!/usr/bin/env bash
# .github/skills/sail-dev-workflow/scripts/check-env.sh
# One-shot health check for all LedgerLeap development services.
# Usage: bash .github/skills/sail-dev-workflow/scripts/check-env.sh

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
SAIL="$PROJECT_ROOT/vendor/bin/sail"

ok()   { echo "  ✅  $1"; }
warn() { echo "  ⚠️   $1"; }
fail() { echo "  ❌  $1"; }

echo ""
echo "=== LedgerLeap Environment Health Check ==="
echo ""

# 1. Docker running?
if docker info > /dev/null 2>&1; then
  ok "Docker daemon is running"
else
  fail "Docker daemon is NOT running — start Docker Desktop"
  exit 1
fi

# 2. Sail containers
if "$SAIL" ps 2>/dev/null | grep -q "Up"; then
  ok "Sail containers are up"
else
  warn "Sail containers may be down — run: ./vendor/bin/sail up -d"
fi

# 3. MySQL
if "$SAIL" artisan db:show --json 2>/dev/null | grep -q '"driver"'; then
  ok "MySQL connection OK"
else
  fail "MySQL connection FAILED"
fi

# 4. Redis
if "$SAIL" exec redis redis-cli ping 2>/dev/null | grep -q "PONG"; then
  ok "Redis is responding"
else
  warn "Redis not responding"
fi

# 5. Embedding service (RAG)
EMBEDDING_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health 2>/dev/null || echo "000")
if [ "$EMBEDDING_STATUS" = "200" ]; then
  ok "Embedding service (port 8000) is up"
else
  warn "Embedding service not responding (status: $EMBEDDING_STATUS) — RAG_ENABLED tests will require mocking"
fi

# 6. VLM service
VLM_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/health 2>/dev/null || echo "000")
if [ "$VLM_STATUS" = "200" ]; then
  ok "VLM service (port 8080) is up"
else
  warn "VLM service not responding (status: $VLM_STATUS)"
fi

# 7. .env exists
if [ -f "$PROJECT_ROOT/.env" ]; then
  ok ".env file exists"
else
  fail ".env file MISSING — copy from .env.example"
fi

# 8. RAG_ENABLED
RAG_ENABLED=$(grep "^RAG_ENABLED" "$PROJECT_ROOT/.env" 2>/dev/null | cut -d= -f2 | tr -d '[:space:]' || echo "unset")
echo "  ℹ️   RAG_ENABLED=$RAG_ENABLED"

# 9. Node modules
if [ -d "$PROJECT_ROOT/node_modules" ]; then
  ok "node_modules present"
else
  warn "node_modules missing — run: sail npm install"
fi

echo ""
echo "=== Check complete ==="
echo ""

