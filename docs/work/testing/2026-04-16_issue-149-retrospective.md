# Issue #149 Retrospective — 2026-04-16

## Summary
Issue #149 revealed a suite-order-sensitive failure pattern in tenant-aware tests and a separate RAG permission test that became flaky under the full suite.

## What we learned

### Process / approach
- When a tenant-aware test passes in isolation but fails in the full suite, first check whether the shared testing connection has drifted before changing the feature code.
- If a Livewire component must recover after `tenancy()->end()`, load the target model without tenant scoping first, then reinitialize tenancy from that model's `tenant_id`.
- Retry-based fixes should be treated as temporary if the root cause is database connection drift or test setup leakage.

### Concrete technique
- `tests/Traits/RefreshDatabaseWithTenant.php` should restore the testing DB connection at the beginning of setup, not only on the first class run.
- `app/Livewire/Ledger/LedgerHistoryManager.php` should use `Ledger::withoutTenancy()->findOrFail()` before tenant restoration when the component must recover from a missing tenant context.
- The RAG permission test was stabilized by waiting for indexed results under the full suite, but the broader lesson is to confirm that the search/index environment is not leaking state between classes.

## Evidence
- `tests/Feature/Ledger/LedgerHistoryListTest.php`
- `tests/Feature/RagSearchServiceTest.php`
- `tests/Feature/Livewire/Ledger/LedgerHistoryManagerTest.php`
- `tests/Traits/RefreshDatabaseWithTenant.php`
- `app/Livewire/Ledger/LedgerHistoryManager.php`

## Retire / keep
- Keep: tenant recovery from `tenant_id` fallback, database connection restoration at test setup.
- Retire: assuming a single isolated test pass is enough to validate tenant-aware and RAG-related behavior.

## Follow-up
- If the same pattern appears again, promote a short troubleshooting checklist into the relevant tenant-context skill or test runbook.

