# Performance Impact

| Metric | Before | After | Driver |
|---|---|---|---|
| `textarea` cache hit | ~14ms (file I/O) | ~1ms (memory) | `logPerformance` JSON buffering |
| `textarea` cache hit | ~5ms (Redis) | ~0.01ms (memory) | `Cache::memo()` |
| `auto_number` median | 4.1ms | 0.63ms | `Cache::memo()` |
| `text` median | 3.2ms | 0.63ms | `Cache::memo()` |

## Evidence

- `app/Services/ConfidentialityLevelService.php` — tenant-aware key + tags
- `app/Models/Organization.php` — `saved`/`deleted` cache flush
- `app/Models/Role.php` — `saved`/`deleted` cache flush
- `app/Services/Ledger/ColumnHtmlService.php` — `Cache::memo()` + early return
- `.github/instructions/php-laravel.instructions.md` — Multi-Tenant Cache section
- `.github/skills/tenant-aware-cache-design/references/column-html-service-cache.md` — full implementation walkthrough
