---
name: ledger-detail-header
description: Unified header layout and dynamic expand/collapse state management for Ledger detail / edit / create pages. Use when building or refactoring ledger page headers, global expand toggles, or mandatory indicators.
compatibility: LedgerLeap (Mary UI, Alpine.js, x-mary-card, ledgerState store)
---

# ledger-detail-header

## Unified Header Structure

All ledger pages (detail, edit, create) use the same base `<x-mary-card class="bg-primary/30">` header.

- **Background**: `bg-primary/30` for visual anchor weight.
- **Common slots**:
  - `title`: breadcrumbs + meta info on one line
  - `default`: collapsible description/guideline via `x-collapse`
  - `menu`: global actions (expand-all toggle, export) — detail only

## Title Expression Rules

| Page | Expression | Example |
|------|------|-----|
| Detail | Ledger title only | `Ledger A` |
| Edit | `Edit — Ledger title` | `Edit — Ledger A` |
| Create | `New — Ledger title` | `New — Ledger A` |

- `h2` uses `flex` for horizontal layout.
- Action text (Edit/New) uses `text-base-content/50`.
- Separator: daisyUI `<span class="divider divider-horizontal"></span>`.
- No supplementary context row below the title; everything stays in the title line.

## Metadata Areas

### Detail
- Version badge (`bg-primary/10`)
- Modifier user card popover
- Updated-at timestamp

### Edit
- Workflow status badge (`bg-warning/10`)
- Next version number

### Create
- Minimal or no metadata; focus on target ledger type.

## Anti-Patterns

- **[PROTECT]** Do not change header card background from `bg-primary/30` to `bg-base-100`.
- **[CLEANUP]** When wrapping header in `x-collapse`, remove old accordion components (`<x-expandable-content>` etc.) internally.
- **[SYNCHRONIZATION]** Do not wait for page reload for Alpine.js updates. Always implement reactive sync (`checkStorage` etc.).
- **[FOOTER REVIEW]** When reviewing detail header, also check persistent footer / action bar for badge-first status and tooltip wording.
- **[LEGACY HEADER]** Do not use old `<x-slot name="header">` + `ttl_3d5 warn` + `bg-warning/40` on edit/create pages. Unify to `<x-mary-card class="bg-primary/30">`.
- **[REDUNDANT CONTEXT]** Do not add a standalone "Editing" / "Creating" context row below the title. Express action state inside the title line via `text-base-content/50`.

## Patterns

| Pattern | Reference |
|---|---|
| Full header Blade examples (detail/edit/create) | [references/header-examples.md](references/header-examples.md) |
| Global state management (`ledgerState` store) + mandatory indicator dot | [references/global-state-and-indicator.md](references/global-state-and-indicator.md) |

## Freshness

- status: confirmed
- last_confirmed_at: 2026-05-06
- recheck_after: 90d
- recheck_trigger: new ledger page type appears (duplicate, bulk-edit), title expression rules change, or Mary UI / daisyUI card/icon guidance changes upstream
