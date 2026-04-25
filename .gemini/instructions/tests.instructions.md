---
applyTo: "tests/**"
---

# Test Rules for LedgerLeap

## Runtime Rule

- **Tests must run inside Laravel Sail or a Docker-based PhpStorm interpreter.**
- Host-side commands such as `php artisan test` / `./vendor/bin/pest` are unsupported for LedgerLeap and must be replaced.
- `./vendor/bin/sail pint` → error check (`last-error` / `browser-logs`) → **Identify and run affected tests** (`./vendor/bin/sail test <path>`) → `/git-commit` → `/skill-maintenance`
- **View changes MUST be verified by rendering tests or browser interaction.** Structural Blade changes often break `route()` generation or variable scopes.
- Reason: testing DB host resolution (`mysql_testing` → `mysql`) is Docker-network based; host execution causes false-negative infrastructure failures before the actual test logic runs.
- Laravel parallel testing already derives worker DB names from the base database using `ParallelTesting::token()`. Do **not** manually rewrite `mysql_testing` to `..._test_{token}` in shared bootstrap code; keep CI grants aligned with the actual worker DBs instead.

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

## Filament Relation Manager Tables

- When a Filament relation-manager table is deferred or the snapshot starts unloaded, call `->loadTable()` before `assertCanSeeTableRecords()`.
- If `assertCanSeeTableRecords()` sees a table but the snapshot has no `table.records.*` keys, load the table first rather than widening the assertion.

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
- When a Livewire component builds tenant-scoped URLs in Blade or computed properties, add one regression test for missing tenant context (for example `tenancy()->end()` or `['tenantId' => null]`) and assert the shared tenant resolver falls back to the model `tenant_id`.

## MCP Tool Test Harness

- MCP unit tests that create tenant-scoped models should prefer `RefreshDatabaseWithTenant`
- MCP unit tests that only validate tool auth/response shape and do **not** persist tenant-scoped data can skip `RefreshDatabaseWithTenant`; prefer an in-memory `User::factory()->make()` plus `Sanctum::actingAs()` for `mcp:*` auth when tenant DB setup would only add cost
- When the test creates a `User` and binds `WritableFolderRepository`, stub both
  `clearAllCache()` and `refreshAllCache()` because `User` model events call them
- For token-authenticated MCP tools, mock permission checks with `Mockery::type(User::class)`
  rather than assuming the factory-created user instance is reused after token auth

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

## Responsibility on Change

- **Any change (Logic, Config, or Views) MUST be validated with relevant tests.**
- Before committing:
    1. Identify affected components and their corresponding tests.
    2. Search for related tests (e.g. `tests/Feature/Livewire/...`).
    3. Run tests via `./vendor/bin/sail test <path-to-test>`.
- **View-only changes are NOT exempt.** Structural changes in Blade can break route generation, variable scopes, or Livewire hydration. Verify that the view renders correctly in tests.
- If a test fails due to a structural change, evaluate if the change is a regression or if the test itself needs an update.

## Evidence Recording

- Final reports (Walkthroughs) MUST include evidence of successful test execution (logs or browser screenshots).
- Never report "Completed" without confirming that existing regressions haven't been introduced.
