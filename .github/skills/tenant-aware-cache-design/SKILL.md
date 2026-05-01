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

## Evidence

- `app/Services/ConfidentialityLevelService.php` — tenant-aware key + tags
- `app/Models/Organization.php` — `saved`/`deleted` cache flush
- `app/Models/Role.php` — `saved`/`deleted` cache flush
- `.github/instructions/php-laravel.instructions.md` — Multi-Tenant Cache section

## Related Skills

- `permission-model` — WritableFolderRepository cache patterns
- `livewire-tenant-context` — Tenant null handling in render()

## Freshness

- `status`: confirmed
- `last_confirmed_at`: 2026-05-01
- `recheck_after`: 90d
- `recheck_trigger`: a new Service adds caching, CACHE_DRIVER changes, or a cache leak is reported across tenants
