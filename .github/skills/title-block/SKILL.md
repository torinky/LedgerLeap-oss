---
name: title-block
description: Designs compact page title blocks and top-of-page context headers for LedgerLeap. Use when building a new page shell, adding breadcrumbs, version labels, compact metadata, primary actions, or collapsible guidance above the fold.
compatibility: LedgerLeap (Mary UI, daisyUI v5, TailwindCSS v4, Livewire v3)
---

# title-block

## Decision Tree

```text
Is this the top visible block of a page or section?
├─ No → use another layout or content skill.
└─ Yes
   ├─ Is the page detail-heavy and anchored around one record?
   │   ├─ Yes → prefer `ledger-detail-header` for the detail header pattern.
   │   └─ No → continue here.
   ├─ Does the block need breadcrumbs, compact metadata, or one primary action?
   │   ├─ Yes → keep them in one row or one compact card header.
   │   └─ No → keep the block minimal.
   ├─ Does the block include long guidance or explanatory text?
   │   ├─ Yes → move it into collapse / progressive disclosure.
   │   └─ No → keep the title block short.
   └─ Does the page need a reusable shell for future pages?
       ├─ Yes → record the pattern in `docs/work/ui-ux/*`.
       └─ No → keep the implementation local and simple.
```

## Core Rules

- Keep the first viewport compact and context-rich.
- Use Mary UI components first, especially card, breadcrumbs, button, badge, and icon components when they fit.
- Use daisyUI semantic classes for the title block role: `card`, `card-body`, `badge`, `tooltip`, `join`, `btn`, and `collapse` when helpful.
- Put the page title where users immediately understand what the page is.
- Put compact metadata next to the title only when it adds immediate context.
- Put one primary action near the title if the page needs it.
- Put long guidance below the fold or behind a collapse.
- Do not add decorative height just to make the top area feel fuller.

## Practical layout guidance

- Prefer one compact card or one compact header band instead of multiple stacked banners.
- Prefer a single row with flexible wrap over several separate blocks when the content is short.
- If the title row includes badges or metadata, keep them short enough to scan quickly.
- If a control is secondary, make it visually lighter than the title and primary action.
- If the block is for a list or search page, keep the title block thin so the results area stays visible.

## Evidence and references

- Repo evidence: `docs/work/ui-ux/2026-04-18_design-workflow-reorganization-note.md`
- Repo evidence: `docs/work/ui-ux/2026-04-18_ledger-detail-ui-redesign-retrospective.md`
- Related skill: `ledger-detail-header`
- Official references that shaped this pattern:
  - daisyUI semantic component classes
  - GOV.UK title guidance
  - Apple HIG button and label guidance

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-18
- recheck_after: 90d
- recheck_trigger:
  - a new page-shell pattern appears in more than one feature
  - the detail header pattern changes
  - daisyUI or Mary UI component guidance changes upstream

