---
name: tenant-aware-cache-design
description: Design cache keys, tags, and invalidation strategies for multi-tenant Laravel apps using Redis. Prevents cross-tenant cache leaks and stale data.
compatibility: "LedgerLeap (Laravel 12, Redis, Stancl Tenancy)"
---

# tenant-aware-cache-design

## When to use

Adding cached tenant-scoped queries, fixing cross-tenant leaks, designing invalidation for global models, or choosing `Cache::remember()` vs `Cache::tags()`.

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
static::saved(fn () => Cache::tags(['confidentiality'])->flush());
static::deleted(fn () => Cache::tags(['confidentiality'])->flush());
```

### Event choice: saved vs updated
- `saved`: fires on both **created** and **updated**. Use this when new records must also invalidate cache.
- `updated`: fires only on **existing record updates**. Use this when creation is handled separately.

**Trap**: Role originally used `updated` only. New roles were created without cache clearing. Fixed by switching to `saved`.

## Redis Requirement

`Cache::tags()` requires a tag-aware driver:
- ✅ **Redis** — full support
- ❌ **Array / File / Database** — no tag support

See [references/redis-testing-fallback.md](references/redis-testing-fallback.md) for driver verification and test fallback patterns.

## Service Cache Wrapper Pattern

```php
$tenantId = tenant()?->id ?? 'global';
$cacheKey = "confidentiality:{$tenantId}:scopes";
return Cache::tags(['confidentiality', 'tenant_access'])
    ->remember($cacheKey, $ttl, fn () => /* query */);
```

## Advanced Patterns

| Pattern | When to read |
|---|---|
| `Cache::memo()` request-level memoization | [references/cache-memo-pattern.md](references/cache-memo-pattern.md) |
| Early-return cache (skip heavy init on hit) | [references/early-return-cache-pattern.md](references/early-return-cache-pattern.md) |
| Invalidation with memoization | [references/invalidation-patterns.md](references/invalidation-patterns.md) |
| Performance benchmarks | [references/performance-impact.md](references/performance-impact.md) |
| Redis testing fallback (non-Redis drivers) | [references/redis-testing-fallback.md](references/redis-testing-fallback.md) |
| ColumnHtmlService full walkthrough | [references/column-html-service-cache.md](references/column-html-service-cache.md) |

## Related Skills

- `permission-model` — WritableFolderRepository cache patterns
- `livewire-tenant-context` — Tenant null handling in render()
- `browser-har-analysis` — Measuring cache hit effectiveness

## Freshness

- `status`: confirmed
- `last_confirmed_at`: 2026-05-01
- `recheck_after`: 90d
- `recheck_trigger`: a new Service adds caching, CACHE_DRIVER changes, or a cache leak is reported across tenants
