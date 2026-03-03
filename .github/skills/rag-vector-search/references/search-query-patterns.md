# Search Query Patterns

## Mroonga MATCH() AGAINST() rules

```php
// ✅ Single-column MATCH — correct
->whereRaw("MATCH(title) AGAINST(? IN BOOLEAN MODE)", [$query])

// ✅ Multiple columns — use OR, not composite index
->where(function ($q) use ($query) {
    $q->whereRaw("MATCH(title) AGAINST(? IN BOOLEAN MODE)", [$query])
      ->orWhereRaw("MATCH(content) AGAINST(? IN BOOLEAN MODE)", [$query]);
})

// ❌ Composite index — DOES NOT WORK with Mroonga
->whereRaw("MATCH(title, content) AGAINST(? IN BOOLEAN MODE)", [$query])
```

## Hybrid search order (MCP search tool)

```
1. Mroonga keyword match (title + content via OR)
2. Vector cosine similarity (ledger_chunks table)
   → weighted by rag.search.weight config
3. composite_score tiebreaker
   → activity_score + freshness_score + importance_score
```

## order_by="semantic_score" usage

When `order_by=semantic_score` is passed to the MCP search tool:
- Requires `RAG_ENABLED=true` and embedding container running
- Falls back to keyword + composite_score if no chunks exist for the ledger

```php
// In MCP SearchLedgersTool
if ($orderBy === 'semantic_score' && config('rag.enabled')) {
    // vector sort path
} else {
    // keyword + composite_score path
}
```

## Scoring weights (config/ledgerleap.php)

```php
// composite_score components
'scoring' => [
    'activity_weight'   => 0.4,
    'freshness_weight'  => 0.3,
    'importance_weight' => 0.3,
]

// importance_score by WorkflowStatus:
// PENDING_APPROVAL => 100
// PENDING_INSPECTION => 60
// DRAFT => 20
// APPROVED => 10
// NONE => 0
```

## Debugging low/zero composite_score

```bash
# Recalculate all scores from ActivityLog
./vendor/bin/sail artisan scoring:calculate

# Reset and recalculate (destructive — clears existing scores)
./vendor/bin/sail artisan scoring:reset --force

# Per-tenant
./vendor/bin/sail artisan scoring:calculate --tenant=testa
```

## Reference files

- `app/Mcp/Tools/SearchLedgersTool.php`
- `app/Services/Scoring/CompositeScoreCalculator.php`
- `app/Services/Scoring/ImportanceScoreService.php`
- `config/rag.php`

