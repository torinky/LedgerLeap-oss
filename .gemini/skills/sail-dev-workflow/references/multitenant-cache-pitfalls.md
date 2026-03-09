# Multi-Tenant Cache Key Pitfalls

## Problem

In a multi-tenant Sail environment, `Cache::remember($key, ...)` shares the same key
across all tenants unless the tenant ID is explicitly included.

**Symptom (Issue #76):** After refactoring `AutoLinkService::getVirtualAutoNumberLinks()`
to delegate to `AutoNumberPatternService::getPatterns()`, auto-links stopped generating
for tenants that have `auto_number` columns. A tenant with no `auto_number` columns was
initialized first, caching 0 results under the plain key. All subsequent tenants received
that stale 0-result cache.

## Fix Pattern

```php
// ❌ WRONG — shared across all tenants
$cacheKey = 'auto_number_patterns';

// ✅ CORRECT — isolated per tenant
$tenantId = tenant()?->id ?? 'global';
$cacheKey = "auto_number_patterns:{$tenantId}";
```

Apply to **every** `Cache::remember()` / `Cache::tags()->remember()` call whose data
originates from tenant-scoped models (e.g. `LedgerDefine`, `AutoLink`, `Folder`).

## Delegation Chain Risk

When a cached method A delegates to cached method B, **both** must include the tenant ID:

```
getVirtualAutoNumberLinks()  → cache key must include tenantId
  └── getPatterns()          → cache key must also include tenantId
```

If only one layer is fixed, the other layer still poisons the cache.

## Verification

```php
// In tinker — confirm isolation
Cache::tags(['auto_links'])->flush();

tenancy()->initialize(Tenant::find('uuid-tenant-without-columns'));
app(AutoNumberPatternService::class)->getPatterns()->count(); // → 0

tenancy()->initialize(Tenant::find('demo-tenant'));
app(AutoNumberPatternService::class)->getPatterns()->count(); // → 4 (not 0)
```

## Affected Cache Tags

| Tag | Methods that MUST include tenantId |
|---|---|
| `auto_links` | `getPatterns()`, `getVirtualAutoNumberLinks()`, any `AutoLink` query cache |
| `permissions` | Handled by `flushAllUserPermissionsCache()` (see permission-model skill) |

## ColumnDefine options default in tests

When writing Feature tests for `auto_number` extraction, pass explicit `options`:

```php
// ❌ options defaults to [] → prefix='', digits=3 → pattern matches bare digits only
new ColumnDefine(0, '設備番号', 'auto_number', 1)

// ✅ explicit options → pattern matches 'EQ-001' correctly
new ColumnDefine(0, '設備番号', 'auto_number', 1, ['prefix' => 'EQ-', 'digits' => 3, 'revision' => ''])
```

