---
applyTo: "tests/**"
---

# Test Rules for LedgerLeap

## Database Trait Selection

```
Need Mroonga full-text search (MATCH AGAINST)?
  YES → DatabaseMigrationsOnce + #[Group('database-migrations')]
Need cross-tenant boundary ($tenantA->run() / $tenantB->run())?
  YES → DatabaseMigrations + #[Group('database-migrations')]
Need tenant context?
  YES → RefreshDatabaseWithTenant
  NO  → RefreshDatabase
```

**Never use `DatabaseMigrations` in unit/feature jobs** — `migrate:rollback` destroys shared tenant DB.

## External Service Isolation

**By default, `Queue::fake()` is enabled in all tests** (`TestCase::$fakeQueue = true`).

Set `$fakeQueue = false` only when: using `Bus::fake()`, asserting `Queue::assertPushed()`, calling `->handle()` directly, or `#[Group('external')]` real-container tests.

```
Test takes 60s then fails? → Missing Queue::fake() — Embedding container (http://embedding:8000) unreachable
  FIX: ensure $fakeQueue = true (default) OR add #[Group('external')]
Test fails in 0s? → Previous test's migrate:rollback destroyed DB
  FIX: database-migrations-test-optimization trait selection
```

**Always use `dispatch()` — never call external services directly in Observers/Services.**

## Permission Setup in Tests

- `assignRole()` / `grantPermission()` MUST be called **before** `actingAs()`
- After Role/Org change → both `flushAllUserPermissionsCache()` + `TenantAccessService::clearAllCache()`
- `tenancy()->initialize($tenant)` MUST be called in every Feature test `setUp()`

## Workflow / LedgerDiff in Tests

```php
// ❌ latestDiff() returns null — factory does NOT update Ledger.latest_diff_id
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);

// ✅ Required pattern
$diff = LedgerDiff::factory()->create(['ledger_id' => $ledger->id]);
$ledger->update(['latest_diff_id' => $diff->id]);
$ledger = $ledger->fresh();
```

## Content Array Access

- `$ledger->content[1]` — direct array access only
- **Never** `data_get($ledger->content, '1')` — AsColumnArrayJson cast breaks this
- Test data must fill all indices 0..maxColumnId (no gaps)
- `content_attached` requires `[0 => []]` sentinel at index 0

## Required Declarations

```php
#[CoversClass(MyComponent::class)]  // Required — PHPUnit won't attribute coverage without it
class MyTest extends TestCase {}
```

## CI Job Structure

| Job | Scope |
|---|---|
| `unit` | `--exclude-group=external,database-migrations` |
| `feature` | `--exclude-group=external,database-migrations` |
| `db-migrations` | `--group=database-migrations` only |

See `.github/skills/database-migrations-test-optimization/SKILL.md` for trait implementation.
See `.github/skills/test-external-dependency-isolation/SKILL.md` for queue fake patterns.
See `.github/skills/permission-model/SKILL.md` for ACL cache patterns.

