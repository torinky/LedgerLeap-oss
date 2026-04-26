# 2026-04-20 Ledger History Split-Pane Retrospective

## Goal

Refresh the ledger history tab so it follows the design baseline:

- use Mary UI cards for the split-pane shell
- keep user-facing copy in translation keys
- scope loading states to the actions that actually mutate the pane
- keep icon-only status markers accessible

## What changed

- The left history list and right diff viewer were wrapped in Mary UI card shells.
- Pane titles, labels, and metadata moved from hardcoded text to translation keys.
- The current-version marker stayed icon-only, but gained tooltip and sr-only text.
- Loading was reduced from full staged skeleton swaps to scoped overlays and opacity states.
- The design instructions were updated to say that split-pane surfaces should prefer Mary UI cards, accessible icon-only markers, and explicit `wire:target` values.
- A work note was added so the split-pane decision has repo evidence.

## What worked

- Mary UI cards fit the list/detail split better than raw Tailwind wrappers.
- Translation-key usage made the visible copy consistent and easier to audit.
- The explicit `wire:target` values stopped unrelated Livewire actions from flickering the whole pane.
- The icon-only current-version badge stayed compact without losing accessibility.
- The feature test still passed after the UI and loading changes.

## What went wrong

- The first pass followed nearby markup too closely and copied behavior before re-checking the design baseline.
- A staged skeleton swap was briefly used for internal updates, which made the UI feel like it was switching states instead of lightly updating.
- Some labels started as hardcoded text such as `Ver.` and `不明` before being moved back to translation keys.
- One loading attribute was mistyped as `wire:loading.target`, which needed to be corrected to `wire:target`.
- The initial card conversion left the view in a partially inconsistent state until the layout was re-checked.

## Final state

- The history list now stays mounted and dims during selection or display-level changes.
- The diff viewer also stays mounted and uses a scoped overlay instead of a full skeleton replacement.
- The user can switch display level or select history rows without the whole pane being torn down.

## Verification

- `./vendor/bin/sail test tests/Feature/Livewire/Ledger/LedgerHistoryManagerTest.php`
- Result: 8 passed

## Guardrails

- For future split-pane work, check the design instructions before copying nearby markup.
- Prefer Mary UI card shells when a list/detail surface already exists.
- Use translation keys for all visible labels and metadata.
- Keep loading feedback scoped to the action that actually changed the content.
- When a badge is icon-only, pair it with tooltip text and sr-only text.
