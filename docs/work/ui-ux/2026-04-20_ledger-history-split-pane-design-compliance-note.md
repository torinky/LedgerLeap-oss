# 2026-04-20 Ledger History Split-Pane Design Compliance Note

## Goal

Bring the ledger history detail surface back in line with the design baseline:

- prefer Mary UI cards for split-pane shells
- keep labels and headings in translation keys
- scope Livewire loading states with explicit targets
- keep icon-only state markers accessible

## Evidence record

```yaml
claim: Ledger history split-pane surfaces should use Mary UI card shells, translation keys, scoped Livewire loading targets, and accessible icon-only state markers.
status: draft
last_confirmed_at: 2026-04-20
recheck_after: 90d
recheck_trigger:
  - a split-pane history/detail page is added or restyled
  - a Mary UI card replacement is needed for a dense list/detail surface
  - a loading state changes shape without a matching wire:target
sources:
  - type: repo-proof
    path: .github/instructions/design.instructions.md
  - type: repo-proof
    path: .github/skills/livewire-loading-ui/SKILL.md
  - type: repo-proof
    path: resources/views/livewire/ledger/ledger-history-manager.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger/workflow-history-list.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger/show.blade.php
  - type: repo-proof
    path: resources/views/components/ledgerDefine/header.blade.php
notes: The first pass drifted toward feature-first markup reuse. The repair was to re-anchor on the design instructions, then standardize the split-pane shell with x-mary-card, translate visible labels, and scope Livewire loading states to the actions that actually mutate the pane.
```

## Root cause

The drift happened because the first implementation pass favored nearby markup and feature behavior over the design baseline. That led to three predictable misses:

- plain Tailwind wrappers where a Mary UI card already fit the pattern
- hardcoded visible labels instead of translation keys
- loading states that were broader than the actions they represented

## Fix applied

- The history list and detail panes now use Mary UI card shells.
- Pane titles and labels are translated.
- Loading overlays and row loading states use explicit `wire:target` values.
- Current-version markers remain icon-only, but now include tooltip and sr-only text.

## Guardrail

For future split-pane surfaces, check the design instructions and the matching helper skills before editing the Blade file. If the pattern is reusable, record the decision in `docs/work/ui-ux/*` before promoting it elsewhere.
