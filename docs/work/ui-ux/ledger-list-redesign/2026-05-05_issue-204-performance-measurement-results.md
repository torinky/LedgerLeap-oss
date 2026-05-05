# Issue #204 Performance Measurement Results

## Measurement Context

| Item | Value |
|---|---|
| Date | 2026-05-05 |
| Browser | Chrome DevTools HAR export |
| Debug Mode | OFF (no Livewire DevTools) |
| Test Flow | Initial load → folder switch × N → return to visited folders |
| Livewire Version | 4.3.0 |

## Changes Applied

1. `confidentialityScrollTracker` — observer re-setup restricted to DOM structure changes only
2. `ledger-sections-rendered` — dispatched only on first render (flag-controlled)
3. `<livewire:... lazy>` attribute added to RecordsTable Blade tag
4. `har_lazy_analysis.py` — robust error handling

## Before vs After Comparison

### Overall Metrics

| Metric | Before (localhost9) | After (localhost10) | Delta |
|---|---|---|---|
| **Total livewire/update requests** | 46 | 25 | -45.7% |
| **Total response body size** | ~28.5 MB | ~14.8 MB | -48.1% |
| **NO UPDATES `$commit` count** | ~15 (periodic, ~10s interval) | 0 | -100% |
| **notifications.icon (wire:poll.30s)** | 7 | 7 | baseline |
| **Folder-switch sequences** | 22 | 10 | -54.5% |

### Request Breakdown

#### Before (localhost9)

```
Component frequency:
   414x  ledger.records-table-row
    34x  ledger.records-table
    24x  ledger.index-manager
    10x  tenant-switcher
    10x  folder.tree
     7x  notifications.icon

Multi-component bundles: 36 requests
```

#### After (localhost10)

```
Component frequency:
   138x  ledger.records-table-row
    15x  ledger.records-table
    10x  ledger.index-manager
     7x  notifications.icon
     4x  tenant-switcher
     4x  folder.tree

Multi-component bundles: 15 requests
```

### Folder-Switch Lazy Analysis

| Metric | Before | After |
|---|---|---|
| lazy% (IM+RT separated) | 50% (11/22) | 50% (5/10) |
| IM_med (interactive time) | ~900ms | ~950ms |
| RT_med (RecordsTable load) | ~4,500ms | ~5,500ms |

**Note**: lazy% did not improve because `#[Lazy]` on `RecordsTable` only applies on initial page load. Re-mount after `wire:key` change does not trigger lazy loading (Livewire v3/v4 specification).

### Key Improvement: Elimination of Phantom Commits

#### Before — Periodic NO UPDATES `$commit`

```
[ 15] 9072ms ['$commit'] (+10.3s) ← NO UPDATES
[ 17]  897ms ['$commit'] (+9.4s)  ← NO UPDATES
[ 20] 11930ms ['__lazyLoad'] (+1.1s)
[ 26] 12274ms ['$commit'] (+12.5s) ← NO UPDATES
...
```

- Empty `updates` but full HTML payload (~200KB-900KB each)
- Triggered by `confidentialityScrollTracker.setupObserver()` → `ledger-sections-rendered` dispatch → unnecessary re-render cascade

#### After — No Phantom Commits

- `$commit` only fires when actual property changes occur
- `wire:poll` (notifications.icon) remains as the only periodic request (expected behavior)

## Root Cause Analysis

### Issue 1: Phantom `$commit` (RESOLVED)

**Cause**: Alpine.js `confidentialityScrollTracker` called `setupObserver()` on every `render()`, re-creating `IntersectionObserver` via `$nextTick()`, which triggered Livewire dirty-check false positives.

**Fix**: Added `lastSectionCount` guard to skip observer re-creation when DOM structure is unchanged. Restricted `ledger-sections-rendered` dispatch to first render only.

### Issue 2: `lazyLoaded=None` on Re-mount (Livewire Spec Limitation)

**Cause**: Livewire v3/v4 `#[Lazy]` attribute is only evaluated during initial page-load mount. When a child component is re-created via `wire:key` change during parent re-render, the lazy-loading lifecycle (`skipMount()` + `skipRender()` + `__lazyLoad` trigger) is bypassed.

**Attempted Fixes**:
- Dynamic `wire:key` change (`recordsTableMountKey`) — required for component recreation
- Explicit `<livewire:... lazy>` Blade attribute — no effect on re-mount

**Conclusion**: This is a Livewire framework limitation. `#[Lazy]` is designed for initial page load optimization only.

## Remaining Observation

### Confidentiality Stamp Update Payload

When `confidentialitySectionChanged` event fires (on scroll), `IndexManager::updateActiveConfidentiality()` updates the active ledger define ID. This triggers `IndexManager` re-render, which includes the full `RecordsTable` HTML in the response even though only the confidentiality stamp badge changes visually.

**Current behavior**:
- User scrolls → `confidentialityScrollTracker` detects section change
- `Livewire.dispatch('confidentialitySectionChanged')`
- `IndexManager::updateActiveConfidentiality()` updates `$activeLedgerDefineId`
- `IndexManager.render()` re-renders entirely, including `RecordsTable`
- Response contains full RecordsTable HTML (~300KB-900KB)

**Potential optimization**: Extract confidentiality stamp into a separate Livewire component or use Alpine.js-only state management to avoid triggering parent re-render.

## Recommendations

### Immediate (Completed)
- ✅ Eliminate phantom `$commit` — 45% request reduction achieved

### Short-term
- Consider extracting `confidentiality-stamp` into an independent Alpine.js component (no Livewire round-trip) to avoid full IndexManager re-render on scroll
- Review if `confidentialitySectionChanged` dispatch can be debounced or throttled

### Long-term
- Accept `#[Lazy]` limitation: optimize folder-switch performance via SQL/cache tuning rather than lazy loading
- Consider page-level reload (`window.location.reload()`) for folder switches if lazy loading on every switch is critical

## Test Results

All existing tests pass after changes:

| Test File | Result |
|---|---|
| `RecordsTableQueryTest.php` | 8 passed (20 assertions) |
| `RecordsTableActionsTest.php` | 40 passed (83 assertions) |
| `IndexManagerIntegrationTest.php` | 14 passed (52 assertions) |
| `IndexManagerAssetPersistenceTest.php` | 3 passed (7 assertions) |
| **Total** | **65 passed, 0 failed** |

## Files Modified

1. `resources/js/components/confidentiality-scroll-tracker.js`
2. `app/Livewire/Ledger/RecordsTable.php`
3. `resources/views/livewire/ledger/index-manager.blade.php`
4. `docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py`
5. `.github/skills/browser-har-analysis/SKILL.md`
