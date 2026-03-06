---
description: RAG vector search — rebuild index, debug wrong results, fix EmbeddingService timeouts in CI. Use when search results are unexpected or after changing the embedding model.
---

# rag-vector-search

## Decision Tree

```
Search results wrong or missing?
├─ RAG_ENABLED=true in .env?
│   NO  → only Mroonga keyword search active (no vector component)
│   YES → hybrid: keyword + semantic + composite_score
├─ Changed embedding model (config/rag.php model.active)?
│   YES → existing chunks use old dimension → full re-index required:
│          sail artisan rag:chunk-existing-ledgers --force
├─ New ledgers not in semantic search?
│   YES → sail artisan rag:chunk-existing-ledgers --only-missing
EmbeddingService timeout in CI?
   → Mock EmbeddingService or set RAG_ENABLED=false in phpunit.xml
```

## Re-index Commands

```bash
# Full re-index (after embedding model change)
./vendor/bin/sail artisan rag:chunk-existing-ledgers --force

# Index only missing (safe, no deletion)
./vendor/bin/sail artisan rag:chunk-existing-ledgers --only-missing

# Check chunk status
./vendor/bin/sail artisan rag:chunk-status

# Ledger body only, skip files
./vendor/bin/sail artisan rag:chunk-existing-ledgers --target=ledger --only-missing
```

## When to Re-index

| Event | Action |
|---|---|
| `rag.model.active` changed | `--force` full re-index |
| Bulk ledger import | `--only-missing` |
| Ledger OCR text updated | Observer handles automatically |
| New ledger created | Observer handles automatically |

## Mroonga Constraint

Single-column `MATCH() AGAINST()` only — combine columns with `OR` in separate MATCH calls.

## Checklist

- [ ] `RAG_ENABLED=true` in `.env` (false disables all vector search silently)
- [ ] After model change: `rag:chunk-existing-ledgers --force` run
- [ ] EmbeddingService mocked or `RAG_ENABLED=false` in test phpunit.xml
- [ ] `rag:chunk-status` checked before debugging results

See `.github/skills/rag-vector-search/references/search-query-patterns.md` for query examples.

