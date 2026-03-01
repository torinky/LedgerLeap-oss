---
name: database-migrations-test-optimization
description: Selects the correct Laravel test database trait for LedgerLeap to avoid slow CI or test isolation failures. Use when writing Mroonga full-text search tests, multi-tenant boundary tests, or when tests exceed 300s in CI.
compatibility: LedgerLeap (Laravel 12 / Mroonga / Sail)
---

# database-migrations-test-optimization

## Trait Selection (decision tree)

```
Need Mroonga full-text search (MATCH AGAINST)?
  YES → DatabaseMigrationsOnce  + #[Group('database-migrations')]
  NO  ↓
Need cross-tenant boundary validation ($tenantA->run() / $tenantB->run())?
  YES → DatabaseMigrations      + #[Group('database-migrations')]
  NO  ↓
Need tenant context?
  YES → RefreshDatabaseWithTenant  (parent TestCase handles this automatically)
  NO  → RefreshDatabase
```

## Trait Comparison

| Trait | migrate:fresh | Tenant | Speed | Use case |
|---|---|---|---|---|
| `RefreshDatabase` | transaction substitute | ❌ | ⚡ fast | simple CRUD tests |
| `RefreshDatabaseWithTenant` | once per class (CI) / per test (local) | ✅ | ⚡ fast | standard feature tests |
| `DatabaseMigrationsOnce` | once per class + TRUNCATE | ✅ | 🟡 moderate | **Mroonga full-text search** |
| `DatabaseMigrations` | per test method | ✅ | 🔴 slow | cross-tenant boundary only |

**Why `DatabaseMigrations` is slow**: `migrate:fresh` takes ~13s (Mroonga index rebuild).
10 test methods = 130s. Also `migrate:rollback` destroys other tests' DB state.

## Performance (measured)

| Before | After |
|---|---|
| `LedgerFullTextSearchTest` 9 tests: **117s** | **16s** (migrate:fresh once) |
| `SearchControllerAdditionalTest` 8 tests: **104s** | **32s** |

## CI Job Structure

`.github/workflows/phpunit.yml`:

| Job | Command | Scope |
|---|---|---|
| `unit` | `--testsuite=Unit --exclude-group=external,database-migrations` | no external deps |
| `feature` | `--testsuite=Feature --exclude-group=external,database-migrations` | no external deps |
| `db-migrations` | `--group=database-migrations` | `DatabaseMigrations*` tests only |

**Never mix `DatabaseMigrations` with `unit`/`feature` jobs** — `migrate:rollback` destroys
the shared tenant DB and causes cascading failures.

## DatabaseMigrationsOnce Usage

See [references/trait-usage.md](references/trait-usage.md) for implementation details,
TRUNCATE table list, and `DomainOccupiedByOtherTenantException` fix patterns.

## Checklist

- [ ] Full-text search test uses `DatabaseMigrationsOnce` (not `RefreshDatabase`)
- [ ] `DatabaseMigrations*` tests have `#[Group('database-migrations')]`
- [ ] `tearDown()` calls `tearDownDatabaseMigrationsOnce()` for TRUNCATE cleanup
- [ ] Tenant domain names are unique per class (no `test.localhost` collision)
- [ ] Verified locally: `./vendor/bin/sail pest --group=database-migrations`
