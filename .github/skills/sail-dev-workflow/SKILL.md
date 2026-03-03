---
name: sail-dev-workflow
description: Resolves LedgerLeap Sail environment issues and provides deterministic helper scripts. Use when git commands produce no output inside Sail, when CSS/JS changes are not reflected, when the test DB is in a broken state, or when the VLM/embedding container is not responding.
compatibility: LedgerLeap (Laravel Sail, Docker, bash -c pattern, npm JIT build)
---

# sail-dev-workflow

## Decision Tree

```
git command produces empty output inside Sail context?
│  Plain `cd /path && git ...` is silently swallowed.
│  FIX: bash -c "cd /path && git status"
│  See: git-commit skill for full commit workflow.
│
CSS/JS changes not visible in browser?
│  Tailwind JIT does not auto-rebuild new utility classes.
│  FIX: sail npm run build
│  Symptom: new classes like opacity-50, group-hover:* silently have no effect.
│
Test failures with "table not found" or stale tenant data?
│  FIX: bin/reset-test-db.sh    (drops and recreates test DB)
│
VLM container (port 8080) not responding?
│  FIX: curl http://localhost:8080/health — check response
│       docker compose logs vlm --tail=50
│       bin/vlm-start.sh to restart
│
Embedding container (port 8000) not responding?
│  FIX: curl http://localhost:8000/health
│       docker compose logs embedding --tail=50
│  In tests: mock EmbeddingService or set RAG_ENABLED=false
```

## Common Sail Command Patterns

```bash
# ✅ git inside Sail — ALWAYS use bash -c
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git status"

# ❌ Silent failure
cd /Users/kazutaka/PhpstormProjects/LedgerLeap && git status

# Rebuild JS/CSS (required after adding new Tailwind classes)
./vendor/bin/sail npm run build

# Run tests
./vendor/bin/sail test

# Reset test DB
bash -c "cd /Users/kazutaka/PhpstormProjects/LedgerLeap && bin/reset-test-db.sh"

# Lint
./vendor/bin/sail pint
```

## Environment Health Check

Run `scripts/check-env.sh` for a one-shot status of all services:

```bash
bash .github/skills/sail-dev-workflow/scripts/check-env.sh
```

## Checklist

- [ ] Git commands: use `bash -c "cd /path && git ..."`
- [ ] After new Tailwind class: `sail npm run build`
- [ ] After schema change: re-run `sail artisan migrate`
- [ ] Test DB broken: `bin/reset-test-db.sh`
- [ ] Before commit: `sail pint` (lint) + `sail test` (regression check)

See [references/container-troubleshooting.md](references/container-troubleshooting.md) for VLM/embedding diagnostics.

