<!-- Generated from .github - DO NOT EDIT MANUALLY -->
---
name: rag-vector-search
description: Implements and debugs LedgerLeap's hybrid search (Mroonga keyword + vector semantic + ActivityLog score). Use when search results are unexpected, when rebuilding RAG index after model change, when EmbeddingService times out in CI, or when switching between search modes.
compatibility: LedgerLeap (RAG_ENABLED, EmbeddingService, ProcessLedgerForRagJob, rag:chunk-existing-ledgers)
---

# rag-vector-search

## Decision Tree

```
Search results are wrong or missing?
├─ Is RAG_ENABLED=true in .env?
│   NO  → only Mroonga keyword search is active (no vector component)
│   YES → hybrid search active: keyword + semantic + score
│
├─ Just changed the embedding model (config/rag.php model.active)?
│   YES → existing chunks use old model dimension → re-index required:
│          sail artisan rag:chunk-existing-ledgers --force
│
├─ New ledgers not appearing in semantic search?
│   YES → ProcessLedgerForRagJob not dispatched?
│          LedgerObserver dispatches it automatically on save when RAG_ENABLED=true.
│          If observer was skipped: sail artisan rag:chunk-existing-ledgers --only-missing
│
EmbeddingService connection refused or timeout in CI?
   YES → EmbeddingService calls http://embedding:8000 — not available in CI.
         FIX: Mock EmbeddingService or set RAG_ENABLED=false in phpunit.xml.
         See references/ci-isolation.md
```

## Search Mode Architecture

3-layer hybrid: **Mroonga keyword** (always) → **vector cosine** (`RAG_ENABLED=true`) → **composite_score** tiebreaker.
Single-column `MATCH() AGAINST()` per call; combine with `OR`. See [references/search-query-patterns.md](references/search-query-patterns.md).

## RAG Re-index Commands

```bash
# Re-index all ledgers (after embedding model change)
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force

# Index only ledgers without chunks (safe, no deletion)
./vendor/bin/sail artisan rag:chunk-existing-ledgers --only-missing

# Check chunk status
./vendor/bin/sail artisan rag:chunk-status

# Index specific target (ledger body only, skip files)
./vendor/bin/sail artisan rag:chunk-existing-ledgers --target=ledger --only-missing
```

## When to re-index

| Event | Action needed |
|---|---|
| `rag.model.active` changed in config | `--force` full re-index |
| Bulk ledger import | `--only-missing` |
| Ledger OCR text updated | Observer handles automatically |
| New ledger created | Observer handles automatically |

## Checklist

- [ ] `RAG_ENABLED=true` in `.env` (false disables all vector search silently)
- [ ] After model change: `rag:chunk-existing-ledgers --force` run
- [ ] EmbeddingService mocked or `RAG_ENABLED=false` in tests
- [ ] `rag:chunk-status` checked before debugging search results

See [references/ci-isolation.md](references/ci-isolation.md) for test isolation patterns.
See [references/search-query-patterns.md](references/search-query-patterns.md) for query examples.

