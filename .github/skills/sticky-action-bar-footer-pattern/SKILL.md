---
name: sticky-action-bar-footer-pattern
description: Standardizes LedgerLeap's shared persistent footer shell for edit/create/define/detail surfaces. Use when a page needs a bottom action bar, slot responsibilities, or badge-first status summaries.
compatibility: LedgerLeap (Mary UI, daisyUI v5, TailwindCSS v4, Livewire v3)
---

# sticky-action-bar-footer-pattern

## Decision Tree

```text
Is this a persistent footer, bottom action bar, or always-visible summary area?
├─ No → use another layout or content skill.
└─ Yes
   ├─ Can the page use `x-ledger.sticky-action-bar`?
   │   ├─ Yes → use the shared shell and fill its slots.
   │   └─ No → only create a custom bottom bar if the page truly cannot fit the shared shell.
   ├─ Does the footer need navigation or escape actions?
   │   ├─ Yes → put them in `left`.
   │   └─ No → keep `left` minimal.
   ├─ Does it need primary or workflow actions?
   │   ├─ Yes → put them in `right`.
   │   └─ No → keep the action area compact.
   ├─ Does it need short status, counts, version, or metadata?
   │   ├─ Yes → put it in `footer` and keep it badge-first.
   │   └─ No → do not add a summary row.
   └─ Will the same footer pattern repeat elsewhere?
       ├─ Yes → keep the shared shell and record any new evidence in `docs/work/ui-ux/*`.
       └─ No → keep the implementation local.
```

## Core Rules

- Use `x-ledger.sticky-action-bar` for persistent ledger footers before considering a custom fixed card or ad hoc bottom bar.
- Keep slot responsibilities stable: `left` for escape/navigation, `right` for primary/workflow actions, `footer` for short status summaries.
- Keep footer summaries badge-first; use tooltips or sr-only text for longer meaning.
- Do not render long explanations as a footer label when the content is really a status summary.
- Preserve the shared shell's mobile pull-up behavior, z-index layering, and scroll-occlusion characteristics.
- If the footer also carries counts or other compact metadata, keep them visually minimal and readable.
- Use Mary UI buttons, badges, tooltips, and icons first; let daisyUI semantic classes carry the layout.
- If text or icons look too small in the footer, defer to `responsive-text-icon-sizing` rather than hard-coding tiny sizes.

## Practical layout guidance

- Keep the footer thin enough that it does not fight the main content.
- Group action buttons so the primary action is obvious.
- Keep secondary links lighter than the primary action.
- Avoid duplicate summary panels under the sticky bar.
- Preserve consistent composition across create, edit, define, and detail pages.

## Evidence and references

- Repo evidence: `docs/work/ui-ux/2026-04-22_sticky-action-bar-footer-pattern.md`
- Repo evidence: `resources/views/components/ledger/sticky-action-bar.blade.php`
- Repo evidence: `resources/views/livewire/ledger/show.blade.php`
- Repo evidence: `resources/views/livewire/ledger/workflow-action-buttons.blade.php`
- Repo evidence: `resources/views/livewire/ledger/create-column.blade.php`
- Repo evidence: `resources/views/livewire/ledger/modify-column.blade.php`
- Repo evidence: `resources/views/livewire/ledger-define/edit.blade.php`
- Related guidance: `docs/work/ui-ux/2026-04-11_status-badge-pattern-guidance.md`
- Related skills: `responsive-text-icon-sizing`
- Official references that shaped this pattern:
  - daisyUI semantic component classes
  - Material Design 3 badge and density guidance
  - Apple HIG button and label guidance

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-22
- recheck_after: 90d
- recheck_trigger:
  - a new ledger page introduces a custom fixed footer instead of the shared shell
  - sibling ledger pages change their footer slot composition
  - sticky action bar behavior or responsive breakpoints change

