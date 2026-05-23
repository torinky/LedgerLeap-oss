# Issue #149 Actions Follow-up — 2026-04-18

## Summary
- GitHub Actions run `24600597417` still failed after PR #156 merged the previous #149 follow-up.
- Current failures were:
  - `Tests\Feature\Ledger\LedgerHistoryListTest::component_recovers_when_tenant_context_is_missing`
  - `Tests\Feature\RagSearchServiceTest::search_respects_user_folder_permissions`

## What changed
- `tests/Traits/RefreshDatabaseWithTenant.php`
  - Reset stale tenancy runtime state before every test setup
  - End the current tenant and purge the `tenant` / `mysql_testing` connections before re-initializing the shared tenant
- `tests/Traits/DatabaseMigrationsOnce.php`
  - Apply the same tenancy runtime reset before every db-migrations test setup
  - Rebuild the tenant runtime from a clean state instead of stacking `initialize()` calls across suite order
- `tests/Feature/RagSearchServiceTest.php`
  - Expand per-test cleanup to include folder / ledger-define / permission-side tables used by the permission-scoped RAG assertions

## Why this option was chosen
- `LedgerHistoryListTest` and `RagSearchServiceTest` now fail in different jobs, but both still point to stale tenant runtime state leaking across Actions-only suite order.
- Stancl Tenancy v3 docs recommend ending tenancy before re-initializing it; the traits were still reusing runtime state and re-calling `initialize()` on top of it.
- Mroonga / Groonga docs describe index visibility as immediate after writes, so the remaining RAG failure is better explained by test-state leakage than by another indexing-delay retry gap.

## Proven dead ends
- Treating the remaining RAG failure as only an indexing-delay problem was insufficient after it had already been moved to the db-migrations job.
- Earlier component-only fixes in `LedgerHistoryManager` were not enough because the suite-order leak can happen before the component rebuilds its own state.

## Evidence
- GitHub Actions run: `24600597417`
- Failed jobs:
  - `Feature Tests (serial remainder)` / job `71938515734`
  - `DB Migrations Tests` / job `71938515741`
- Related repo files:
  - `tests/Feature/Ledger/LedgerHistoryListTest.php`
  - `tests/Feature/RagSearchServiceTest.php`
  - `tests/Traits/DatabaseMigrationsOnce.php`
  - `tests/Traits/RefreshDatabaseWithTenant.php`
  - `.github/workflows/phpunit.yml`
- External references checked:
  - Stancl Tenancy v3 docs on initialization / testing / Livewire
  - Groonga and Mroonga docs on immediate index visibility after writes

## Best-effort validation in this session
- PASS: `php -l tests/Traits/RefreshDatabaseWithTenant.php`
- PASS: `php -l tests/Traits/DatabaseMigrationsOnce.php`
- PASS: `php -l tests/Feature/RagSearchServiceTest.php`

## Environment limits encountered
- Local Sail validation could not be completed in this sandbox because the repo starts from a fresh clone without `.env` / installed dependencies.
- `composer install` could not be completed here because one dependency download fell back to GitHub source authentication, which is unavailable in this sandbox.

## Freshness
- status: confirmed-repo
- last_confirmed_at: 2026-04-18
- recheck_after: 30d
- recheck_trigger:
  - if `LedgerHistoryListTest` fails again in Actions serial runs
  - if another RAG / Mroonga persistence test is added to the non-`database-migrations` suite
  - if the tenancy recovery flow in `LedgerHistoryManager` is edited again
