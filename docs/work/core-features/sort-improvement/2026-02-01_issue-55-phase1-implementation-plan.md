# Issue #55: Multi-Ledger Sorting Improvement (Option D) Implementation Plan

## Goal
Enable correct global sorting and pagination for mixed ledger lists by introducing a denormalized `default_sort_value` column. This addresses the limitation of "per-page sorting" in the current implementation.

## User Review Required
> [!IMPORTANT]
> This implementation involves adding a column `default_sort_value` to the `ledgers` table and calculating it for all existing records. This is a potentially heavy operation for large datasets.
> **Phase 1 Strategy:** We will implement the column and logic, then provide a CLI command to regenerate values. The sorting logic will gracefully fallback or treat nulls as empty until data is populated.

## Proposed Changes

### Database & Migration
#### [NEW] `database/migrations/YYYY_MM_DD_HHMMSS_add_default_sort_value_to_ledgers_table.php`
- Add `default_sort_value` (TEXT or VARCHAR, nullable) to `ledgers`.
- Add index on `(ledger_define_id, default_sort_value)` for performance.

### Logic & Models
#### [MODIFY] [Ledger.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Models/Ledger.php)
- Add `generateDefaultSortValue()` method:
  - Iterates through `ledger_define->column_define` sorted by `sort_index`.
  - Concatenates values (e.g., pipe-separated) with normalization (padding numbers, formatting dates).
- Update `$fillable` if necessary (though likely handled via observer).

#### [NEW] `app/Observers/LedgerObserver.php` (if not exists, or modify existing)
- `saving` event: Call `generateDefaultSortValue()` and set `default_sort_value`.
- `updated` event (optional): If we use a Job, dispatch here. For Phase 1, synchronous calculation in `saving` is simpler and likely sufficient unless generation is very heavy (OCR dependency?). *Decision: Synchronous for now as it's just string concatenation.*

#### [NEW] `app/Console/Commands/RegenerateLedgerDefaultSortValues.php`
- Command `ledger:regenerate-default-sort {ledgerDefineId?}`.
- Iterates ledgers and saves them to trigger the observer (or direct update).

### Component & Query
#### [MODIFY] [IndexManager.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Livewire/Ledger/IndexManager.php)
- Logic to determine `orderBy`. If 'default', pass this intent to `RecordsTable`.
- Remove the single-ledger restriction for default sorting.

#### [MODIFY] [RecordsTable.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Livewire/Ledger/RecordsTable.php)
- In `render()`/Query builder:
  - If sorting by 'default', use `orderBy('default_sort_value', 'asc')`.
  - Remove existing PHP-side collection sorting logic if it was added (or ensure we don't add it).

### UI
#### [MODIFY] [table-header.blade.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/resources/views/components/ledger/table-header.blade.php)
- Display badge/indicator for columns that are part of the sort key (using `sort_index`).

## Verification Plan

### Automated Tests (**New Implementation**)
#### [NEW] [MultiLedgerSortTest.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/tests/Feature/Livewire/Ledger/MultiLedgerSortTest.php)
- **Test 1: Sorting consistency across pages.**
  - Create 3 defined ledgers (Date sort, Number sort, Text sort).
  - Create 100 records mixed.
  - Assert that page 1 contains the "smallest" sort values globally, not just a random subset sorted locally.
- **Test 2: Pagination integrity.**
  - Navigate to page 2 and ensure record continuity (no duplicates or skips vs global list).
- **Test 3: Sort Value Generation.**
  - Create a ledger, verify `default_sort_value` is populated in DB.
  - Update a ledger's column value, verify `default_sort_value` updates.

### Manual Verification
1. **Migration:** Run `php artisan migrate`.
2. **Data Gen:** Create sample ledgers with multiple definitions.
3. **UI Check:** Go to "All Ledgers" (or root folder). Verify they appear sorted by their respective keys (e.g., Ledger A by Date, Ledger B by Serial).
4. **Pagination:** Set per-page to small number (e.g., 5). Verify page 2 continues correctly.
