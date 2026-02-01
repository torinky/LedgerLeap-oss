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
  - **Normalization**:
    - Numbers: Zero-padding (e.g., `000000123.45`).
    - Dates: `YYYY-MM-DD`.
    - Text/Files: Truncate to 50 chars, remove newlines/control chars.
  - Concatenates values (pipe-separated) and limits total length to 512 chars.
- Update `$fillable` if necessary.

#### [NEW] `app/Observers/LedgerObserver.php`
- `saving` event: Call `generateDefaultSortValue()` and set `default_sort_value`.

#### [MODIFY] [LedgerImport.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Imports/LedgerImport.php)
- **Constraint**: Uses `WithUpserts`, bypassing observers.
- **Change**: Manually call `generateDefaultSortValue()` inside the `model()` method when creating the `Ledger` instance to ensure `default_sort_value` is populated during import.

#### [NEW] `app/Console/Commands/RegenerateLedgerDefaultSortValues.php`
- Command `ledger:regenerate-default-sort {ledgerDefineId?}`.
- Iterates ledgers and performs `save()` to trigger observer (or direct update for speed if needed).

### UI & Query
#### [MODIFY] [IndexManager.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Livewire/Ledger/IndexManager.php)
- Logic to determine `orderBy`. Always allow switching to 'default' if `defaultSortColumns` exist across any active ledger definitions.

#### [MODIFY] [RecordsTable.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Livewire/Ledger/RecordsTable.php)
- Query builder: If `orderBy === 'default'`, use `orderBy('default_sort_value', $asc)`.

#### [MODIFY] [table-header.blade.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/resources/views/components/ledger/table-header.blade.php)
- **Visual Feedback**:
  - If sorting by a specific column: Highlight that header strongly.
  - If sorting by 'default': Highlight columns based on `sort_index`:
    - Priority 1: Stronger highlight (e.g., `bg-primary/20`).
    - Priority 2: Milder highlight (e.g., `bg-primary/10`).
    - Priority 3+: Faint highlight (e.g., `bg-primary/5`).
  - Use tooltips (`data-tip`) to show "Sort Priority: 1", "2", etc.

#### [MODIFY] [search.blade.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/resources/views/components/ledger/search.blade.php)
- Ensure "Default Sort" option is always visible in the dropdown.

## Verification Plan

### Automated Tests
#### [NEW] [LedgerDefaultSortTest.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/tests/Unit/Models/LedgerDefaultSortTest.php) (Unit)
- Verify `generateDefaultSortValue()` logic:
  - Numeric padding.
  - Date formatting.
  - Text truncation (50 chars) and newline removal.
  - Attachment filename handling.

#### [NEW] [MultiLedgerSortTest.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/tests/Feature/Livewire/Ledger/MultiLedgerSortTest.php) (Feature)
- **Test 1: Global Sort & Pagination**: Create multiple ledger types and records. Assert Page 1 has globally smallest values.
#### [MODIFY] [LedgerDefine/Edit.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Livewire/LedgerDefine/Edit.php)
- **Implement `regenerateDefaultSort` method**:
    - Check if user has `Admin` role.
    - Check lock status (Cache: `ledger_def:{id}:regenerating_sort`).
    - Dispatch `RegenerateDefaultSortJob`.
    - Set temp lock key.

#### [NEW] [RegenerateDefaultSortJob.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/app/Jobs/RegenerateDefaultSortJob.php)
- **Logic**:
    - Acquire cache lock (atomic).
    - Iterate all Ledgers for the definition.
    - Call `generateDefaultSortValue()` and save (or mass update if optimized).
    - Clear cache lock on completion/failure.

#### [MODIFY] [edit.blade.php](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/resources/views/livewire/ledger-define/edit.blade.php)
- Add "Regenerate Default Sort" button (visible only to Admin).
- Show "Processing..." state if lock key exists.

### Verification Plan (Updated)

#### Automated Tests
- [ ] `LedgerDefaultSortTest`: Verify `default_sort_value` generation logic.
- [ ] `RegenerateDefaultSortJobTest`: Verify job execution and locking mechanism.
- [ ] `LedgerDefineEditTest`: Verify Admin check and method invocation.

#### Manual Verification
- [ ] **CSV Import**: Import data with mixed `auto_number` prefixes and verify correct sort order.
- [ ] **UI Highlight**: Confirm gradients for multi-column default sort.
- [ ] **Admin UI**:
    - As Admin: Click "Regenerate", verify job runs, button shows "Processing", then re-enables.
    - As Non-Admin: Verify button is hidden.
    - Double Submit: Verify rapid clicks don't double dispatch (Cache lock).
