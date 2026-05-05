---
name: tenant-aware-cache-design
description: Design cache keys, tags, and invalidation strategies for multi-tenant Laravel apps using Redis. Prevents cross-tenant cache leaks and stale data.
compatibility: "LedgerLeap (Laravel 12, Redis, Stancl Tenancy)"
---

# tenant-aware-cache-design

## When to use

Use this skill when **any** of the following is true:
- Adding a new cached query or Service that reads tenant-scoped data
- Fixing a cache leak where one tenant sees another tenant's data
- Designing cache invalidation when a global model (Organization, Role) changes
- Choosing between `Cache::remember()` vs `Cache::tags()` for a new feature

## Cache Key Naming

### Pattern
```
"{domain}:{$tenantId}:{type}:{id}"
```

### Rules
1. **Always include tenant ID** — even if the model itself does not have `BelongsToTenant`.
   ```php
   $tenantId = tenant()?->id ?? 'global';
   $cacheKey = "confidentiality:{$tenantId}:scopes";
   ```
2. **Use `'global'` fallback** — when `tenant()` is null (console commands, tinker, background jobs).
3. **Domain prefix** — use a short domain name (`confidentiality`, `folder_perm`, `tenant_access`).

### Anti-pattern (learned from WritableFolderRepository)
```php
// BAD — missing tenant ID causes cross-tenant leaks
Cache::remember("folder_permissions:{$user->id}", ...);

// GOOD
Cache::remember("folder_permissions:{$tenantId}:{$user->id}", ...);
```

## Cache Tags

### When to use tags
- The cached data is **shared across multiple keys** and must be cleared together.
- Example: `allScopes()` caches Organization + Role data. When any Org/Role changes, the entire cache must be invalidated.

### Tag design
```php
$cacheTags = ['confidentiality', 'tenant_access'];
```

- **Primary tag** (`confidentiality`): domain-specific. Flushed only when domain data changes.
- **Secondary tag** (`tenant_access`): shared with TenantAccessService. Flushed when tenant-level access changes.

### Tag clearing
```php
// Clear ALL keys tagged with 'confidentiality' across all tenants
Cache::tags(['confidentiality'])->flush();
```

**Trade-off**: `flush()` clears unrelated tenant keys too. For low-churn data (Org/Role lists), this is simpler and safer than per-key tracking.

## Model Event Integration

### Global models without BelongsToTenant
Organization and Role do **not** have `BelongsToTenant`. Their changes affect all tenants, so cache must be cleared globally.

```php
// Organization::booted()
static::saved(function ($organization) {
    Cache::tags(['confidentiality'])->flush();
});

static::deleted(function ($organization) {
    Cache::tags(['confidentiality'])->flush();
});
```

### Event choice: saved vs updated
- `saved`: fires on both **created** and **updated**. Use this when new records must also invalidate cache.
- `updated`: fires only on **existing record updates**. Use this when creation is handled separately.

**Trap**: Role originally used `updated` only. New roles were created without cache clearing. Fixed by switching to `saved`.

## Redis Requirement

`Cache::tags()` requires a tag-aware driver:
- ✅ **Redis** — full support
- ❌ **Array** — no tag support (testing fallback)
- ❌ **File** — no tag support
- ❌ **Database** — no tag support

Verify the driver before designing tag-based invalidation:
```bash
# .env
CACHE_DRIVER=redis
```

If Redis is unavailable in tests, wrap tag calls:
```php
if (Cache::getStore() instanceof \Illuminate\Cache\TaggableStore) {
    Cache::tags(['confidentiality'])->flush();
} else {
    Cache::flush();
}
```

## Service Cache Wrapper Pattern

```php
class ConfidentialityLevelService
{
    public static function allScopes(): array
    {
        $tenantId = tenant()?->id ?? 'global';
        $cacheKey = "confidentiality:{$tenantId}:scopes";
        $cacheTags = config('confidentiality.cache.tags', ['confidentiality', 'tenant_access']);
        $cacheTtl = config('confidentiality.cache.ttl', 3600);

        return Cache::tags($cacheTags)->remember($cacheKey, $cacheTtl, function () {
            // DB query here
        });
    }
}
```

## Laravel 13 `Cache::memo()` — Request-Level Memoization

`Cache::memo()` creates a `MemoizedStore` wrapper that caches resolved values **in memory** for the current request/job. This eliminates repeated Redis round-trips when the same key is read multiple times.

### When to use
- The same cache key is read **>1 time per request** (e.g. table cell rendering)
- Redis latency (~5ms) is larger than the compute time being cached

### Pattern
```php
use Illuminate\Support\Facades\Cache;

// In Service method — reads from Redis on first call, memory on repeats
$cached = Cache::memo()->get($cacheKey);
if ($cached !== null) {
    return $cached;
}

$html = $this->expensiveRender($data);
Cache::memo()->put($cacheKey, $html, $ttl);

return $html;
```

### Why not `static $requestCache`?
- `static` arrays leak across tests and require manual `tearDown()` cleanup
- `Cache::memo()` is managed by Laravel, auto-cleared per request/job
- `flush()` invalidates both memory **and** underlying store consistently

### Test cleanup
```php
protected function tearDown(): void
{
    Cache::memo()->flush();
    parent::tearDown();
}
```

## Early-Return Cache Pattern

When a Service has heavy initialization (`mount()`, DI resolution, etc.), check the cache **before** setup to avoid wasted work.

```php
public function show($columnDefineData, $initialValue, ..., ?Ledger $record = null)
{
    // Convert array to object early so type/id are accessible
    if (is_array($columnDefineData)) {
        $columnDefineData = new ColumnDefine($columnDefineData);
    }

    // Early cache hit: skip mount() and all rendering logic
    if (! $asCreate && ! $highlight && $record && is_object($columnDefineData)) {
        $type = $columnDefineData->type ?? null;
        if (in_array($type, ['textarea', 'auto_number', 'text', 'url', 'number'])) {
            $cacheKey = "column_html:{$type}:{$tenantId}:{$record->id}:{$colId}";
            $cached = Cache::memo()->get($cacheKey);
            if ($cached !== null) {
                // Wrap cached fragment in outer container if needed
                $html = $type === 'textarea'
                    ? '<div class="prose ...">' . $cached . '</div>'
                    : $cached;
                return new HtmlString($html);
            }
        }
    }

    // Cache miss: perform full initialization
    $this->mount($columnDefineData, $initialValue, ...);
    // ... rest of rendering
}
```

## Invalidation with Memoization

When invalidating Redis keys directly (e.g. pattern delete), `MemoizedStore` may still hold stale values in memory. Always flush the memo cache too.

```php
public function clearCacheForLedger(Ledger $ledger): void
{
    // Flush memoized memory FIRST so subsequent reads hit Redis
    Cache::memo()->flush();

    // Then delete underlying Redis keys
    if (Cache::getStore() instanceof RedisStore) {
        $keys = Redis::keys("*column_html:*:{$tenantId}:{$ledger->id}:*");
        if (! empty($keys)) {
            Redis::del($keys);
        }
    }
}
```

## Performance Impact

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

## Related Skills

- `permission-model` — WritableFolderRepository cache patterns
- `livewire-tenant-context` — Tenant null handling in render()
- `browser-har-analysis` — Measuring cache hit effectiveness

## Freshness

- `status`: confirmed
- `last_confirmed_at`: 2026-05-01
- `recheck_after`: 90d
- `recheck_trigger`: a new Service adds caching, CACHE_DRIVER changes, or a cache leak is reported across tenants
