# Issue #149 Actions Follow-up — 2026-04-18

## Summary
- GitHub Actions run `24599067385` still failed in `Feature Tests (serial remainder)` after earlier #149 fixes.
- Current failures were:
  - `Tests\Feature\Ledger\LedgerHistoryListTest::component_recovers_when_tenant_context_is_missing`
  - `Tests\Feature\RagSearchServiceTest::search_respects_user_folder_permissions`

## What changed
- `app/Livewire/Ledger/LedgerHistoryManager.php`
  - Resolve the tenant model from `ledgerRecord->tenant_id`
  - End stale tenancy before re-initializing
  - Prefer model-based re-initialization, then fall back to ID-based initialization
  - Reload the ledger after each successful recovery branch
- `tests/Feature/RagSearchServiceTest.php`
  - Move the test to `DatabaseMigrationsOnce`
  - Mark it with `#[Group('database-migrations')]` so CI runs it in the Mroonga-safe job
  - Replace repeated `Role::create()` calls with `Role::firstOrCreate()`

## Why this option was chosen
- `LedgerHistoryListTest` was failing only in Actions serial runs, which matches stale tenancy / connection state leakage better than a simple query bug.
- `RagSearchServiceTest` uses persisted `ledger_chunks` and Mroonga-backed search assertions, so keeping it in the normal `RefreshDatabaseWithTenant` serial suite was the wrong test isolation strategy.
- Retrying the RAG assertion was already attempted in earlier work and did not remove the Actions-only instability.

## Proven dead ends
- Treating the remaining RAG failure as only an indexing-delay problem was insufficient; the test was still living in the wrong CI job.
- Earlier tenant recovery fixes that only reloaded the ledger or only reinitialized by ID were not enough for the stale serial-suite state seen in Actions.

## Evidence
- GitHub Actions run: `24599067385`
- Failed job: `Feature Tests (serial remainder)` / job `71934521471`
- Related repo files:
  - `app/Livewire/Ledger/LedgerHistoryManager.php`
  - `tests/Feature/Ledger/LedgerHistoryListTest.php`
  - `tests/Feature/RagSearchServiceTest.php`
  - `tests/Traits/DatabaseMigrationsOnce.php`
  - `.github/workflows/phpunit.yml`

## Best-effort validation in this session
- PASS: `php vendor/bin/pint --test app/Livewire/Ledger/LedgerHistoryManager.php tests/Feature/RagSearchServiceTest.php`
- PASS: `php -l app/Livewire/Ledger/LedgerHistoryManager.php`
- PASS: `php -l tests/Feature/RagSearchServiceTest.php`
- PASS: `parallel_validation` code review / CodeQL gate after the code changes

## Environment limits encountered
- Local Sail validation could not be completed in this sandbox because the repo starts from a fresh clone without `.env` / installed dependencies.
- `composer install` required ignoring the local PHP 8.3 host mismatch versus the repo's PHP 8.4 requirement.
- `npm ci` needed `--ignore-scripts` because `chromedriver` download to `googlechromelabs.github.io` was blocked.
- `./vendor/bin/sail up` could not build because the container image provisioning for PHP 8.4 packages failed in this environment.

## Freshness
- status: confirmed-repo
- last_confirmed_at: 2026-04-18
- recheck_after: 30d
- recheck_trigger:
  - if `LedgerHistoryListTest` fails again in Actions serial runs
  - if another RAG / Mroonga persistence test is added to the non-`database-migrations` suite
  - if the tenancy recovery flow in `LedgerHistoryManager` is edited again
