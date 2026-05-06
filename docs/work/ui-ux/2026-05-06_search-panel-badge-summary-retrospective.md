# Ledger search panel badge / form decomposition retrospective

## Context
- Target: `resources/views/components/ledger/search.blade.php`
- Change theme: search panel header, collapsed summary badges, and compact filter rows
- Goal: make the compact header readable while keeping each state visible through iconized badges and translated labels

## What changed in the view
- Primary search input remains the dominant control.
- Sort / per-page stay grouped as the primary query-adjacent controls.
- The collapsed header now acts as a summary strip with iconized badges for:
  - sort direction
  - display level
  - semantic search
  - technical term
  - synonym
  - workflow / approval status
- Each compact filter row keeps a noun label plus an icon and a tooltip hint.

## Component-level reflection

### 1) Whole search panel
**Needs design-skill reflection: YES**
- This is not just a single form tweak; it is a reusable search/list header composition pattern.
- It should be documented in the top-level design guidance and the search-header skill.

### 2) Search input
**Needs design-skill reflection: NO**
- The input already follows the established pattern: one dominant search field with a built-in Mary UI icon.
- No new reusable rule was discovered.

### 3) Sort / per-page controls
**Needs design-skill reflection: YES, but only in the search-header skill**
- The key learning is grouping these as a stable pair next to the search field.
- This is a responsive search-header layout rule, not a general form-layout rule.

### 4) Collapsed header summary badges
**Needs design-skill reflection: YES**
- The summary strip needs to preserve active state even when the filter body is collapsed.
- Badge-first, iconized state is important for scanability.
- Workflow status should remain visible here because it is important contextual state.

### 5) Compact filter rows inside the collapse
**Needs design-skill reflection: YES, in form-layout and design guidance**
- Each row is a compact form affordance.
- The reusable rule is: `label` wrapper + semantic icon + tooltip hint + compact control.
- For toggle rows, keep the wrapper as `label` so the visible text and the toggle stay associated and the full row remains clickable.
- This is broader than the current search panel and can be reused in filter drawers, settings blocks, and other compact control groups.

### 6) Translation / copy
**Needs design-skill reflection: NO**
- The existing translation rule already covers this.
- No new copy policy was discovered.

### 7) Loading / async state
**Needs design-skill reflection: NO**
- No new `wire:loading` or async interaction pattern was introduced.

### 8) Icon sizing / density
**Needs design-skill reflection: PARTIAL**
- Existing `responsive-text-icon-sizing` guidance already fits the need.
- Only a light reminder is needed: keep meaningful icons readable inside dense badges and labels.

## Recommendation
- Promote the reusable part into `.github/instructions/design.instructions.md`.
- Also sync the related skills:
  - `search-header-responsive-layout`
  - `form-layout`
- No update needed for `translation` or `livewire-loading-ui`.

## Evidence
- `resources/views/components/ledger/search.blade.php`
- `resources/views/livewire/ledger/index-manager.blade.php`
- `app/Livewire/Ledger/IndexManager.php`

