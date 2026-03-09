# Container Troubleshooting

## VLM Service (port 8080)

```bash
# Health check
curl http://localhost:8080/health

# View logs
./vendor/bin/sail exec vlm tail -f /var/log/vlm.log
# or
docker compose logs vlm --tail=50 --follow

# Restart
./vendor/bin/sail restart vlm
# or use the helper script
bash bin/vlm-start.sh
```

Common causes of VLM failure:
- GPU memory exhausted → `docker stats` to check
- Model not downloaded → `bin/vlm-start.sh` re-downloads
- Port conflict → `lsof -i :8080`

## Embedding Service (port 8000)

```bash
# Health check
curl http://localhost:8000/health
# Expected: {"status": "ok", "model": "..."}

# Check current model
curl http://localhost:8000/model

# View logs
docker compose logs embedding --tail=50

# Restart
./vendor/bin/sail restart embedding
```

## Test DB Reset

```bash
# Full reset (drops tenant DBs + runs fresh migration + seeds)
bash bin/reset-test-db.sh

# Partial: just re-migrate
./vendor/bin/sail artisan migrate:fresh --seed --env=testing
```

Use `reset-test-db.sh` when:
- Tests fail with "Table 'X' doesn't exist"
- Tenant isolation is broken between test runs
- After adding new migration files to existing test run

## Sail Container Status Overview

```bash
./vendor/bin/sail ps

# Expected running services:
# laravel.test  (PHP/app)
# mysql         (database)
# redis         (cache/queue)
# embedding     (RAG vector search — optional)
# vlm           (OCR/vision — optional)
```

## Queue Worker (for jobs in development)

```bash
# Start queue worker (needed for ProcessLedgerForRagJob, etc.)
./vendor/bin/sail artisan queue:work --tries=3

# Check failed jobs
./vendor/bin/sail artisan queue:failed

# Retry all failed jobs
./vendor/bin/sail artisan queue:retry all
```

## Reference

- `bin/vlm-start.sh` — VLM startup helper
- `bin/reset-test-db.sh` — test DB reset
- `docs/development/vlm-ocr.md` — VLM model switching

