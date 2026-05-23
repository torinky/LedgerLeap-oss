# 2026-04-22 Sticky Action Bar Footer Pattern

## Goal

LedgerLeap の編集画面・新規登録画面・台帳定義画面・台帳詳細画面で使う persistent footer を、`x-ledger.sticky-action-bar` に寄せて統一する。

## Evidence record

```yaml
claim: Persistent footers on LedgerLeap ledger pages should use the shared sticky action bar shell, with left/right/footer slots preserving navigation, primary actions, and short status summary content.
status: confirmed
last_confirmed_at: 2026-04-22
recheck_after: 90d
recheck_trigger:
  - a new ledger page introduces a custom fixed footer instead of the shared shell
  - sibling ledger pages change their footer slot composition
  - sticky action bar behavior or responsive breakpoints change
sources:
  - type: repo-proof
    path: resources/views/components/ledger/sticky-action-bar.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger/show.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger/workflow-action-buttons.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger/create-column.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger/modify-column.blade.php
  - type: repo-proof
    path: resources/views/livewire/ledger-define/edit.blade.php
  - type: repo-proof
    path: .github/instructions/design.instructions.md
```

## Working decision

### 1. Footer shell

- Use `x-ledger.sticky-action-bar` for ledger pages that need a persistent bottom action surface.
- Avoid ad hoc fixed cards or custom bottom bars unless the page genuinely cannot fit the shared shell.
- Keep the shell consistent with sibling pages so scroll occlusion, mobile pull-up behavior, and z-index layering stay predictable.

### 2. Slot responsibilities

- `left`: escape/navigation actions, secondary links, and list/window navigation.
- `right`: primary actions and workflow buttons.
- `footer`: short status summary, counts, current version, or other compact metadata.

### 3. Status presentation

- Prefer badge-first status summaries in the footer area.
- Keep labels short and use tooltip text for the longer explanation.
- If a footer needs both action buttons and status context, group them within the shared shell instead of creating a separate summary panel below it.

### 4. Responsive behavior

- Keep the shared shell responsive on desktop and mobile.
- Preserve the existing pull-up/toggle behavior for small screens when using the standard shell.
- Do not reduce the footer to a single tiny fixed bar if it hides important actions or status information.

## Notes

- This pattern is intentionally broader than the badge guidance note: it covers footer shell selection, action placement, and compact status summary layout.
- Related guidance remains in `docs/work/ui-ux/2026-04-11_status-badge-pattern-guidance.md` for the badge-first part of the footer summary.