# HAR Analysis Scripts

## Standard Command Recipes

### Basic Summary (legacy script)
```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py localhost.har localhost2.har
```
> `livewire-HASH/update` URLs do not match the old pattern, so livewire/update count will be 0. Use `har_lazy_analysis.py` for accurate Livewire analysis.

### Lazy analysis & before/after comparison (recommended)
```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
    localhost.har localhost2.har localhost3.har
```

Output includes:
- Timeline (chronological request list)
- Component frequency
- Multi-component bundles
- Folder-switch sequences (lazy flag, IM time, RT time, interactive/content)
- COMPARISON SUMMARY (cross-file comparison, interactive time median)

Typical output should answer:
- Which request type dominates?
- How many `livewire/update` requests are large?
- Which components are repeated in the payload?
- Is RecordsTable lazy-loaded (separate from IndexManager)?
- Did debug noise disappear between captures?

### Performance log aggregation (use with HAR)
```bash
python3 docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py \
    storage/logs/laravel-YYYY-MM-DD.log
```

Aggregates `column_html_show_ms` entries. Cross-check HAR wait time against server-side bottlenecks.

Output includes:
- render_kind aggregation (count / sum / mean / median / max)
- source aggregation (table-row / ledger-detail-table etc.)
- ledger_id Top 15 (check for cost concentration)
- >20ms spike list (render_kind, ledger, column_id, descending)

| Observation | Implication |
|---|---|
| `textarea` sum > 80% | `MarkdownRenderer` + `AutoLinkService` bottleneck → consider caching |
| `auto_number` > 100ms spike | `AutoLinkService` regex may be slow on specific ledgers |
| `select` / `chk` slow | Missing ColumnDefine eager load |
| wait ≈ total (HAR) | All delay is server-side PHP |
| wait << total (HAR) | Network download is slow (large response body) |
