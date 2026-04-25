<!-- Generated from .github - DO NOT EDIT MANUALLY -->
---
name: search-header-responsive-layout
description: Designs and reviews compact LedgerLeap search headers that stay readable while scrolling. Use when the search card is the page entry point, when sort/per-page controls must remain grouped across intermediate breakpoints, when display-level controls should move into search options, or when a sticky header may hide too much of the results list.
compatibility: LedgerLeap (Livewire v3, Mary UI, daisyUI v5, TailwindCSS v4)
---

# search-header-responsive-layout

## Decision Tree

```text
Is this a sticky search header or top toolbar for a list page?
├─ No → use the normal UI design guidance for the page.
└─ Yes
   ├─ Does the header need to stay visually thin?
   │   ├─ Yes → keep the header to a short brand band, remove extra copy, and minimize vertical padding.
   │   └─ No → you may use a fuller hero-style treatment, but still protect the results list below.
   ├─ Should display density be adjusted from the header?
   │   ├─ Yes → move `displayLevel` into the search options/collapse, where it reads as a secondary control.
   │   └─ No → keep density controls out of the primary strip unless the page is a detail-focused viewer.
   ├─ Do `sort_by` and `per_page` need to feel like one control group?
   │   ├─ Yes → keep them horizontal through intermediate breakpoints and let them collapse only on narrow screens.
   │   └─ No → separate them only if the page structure clearly benefits.
   ├─ Do result counts or page navigation support reading the list rather than filtering it?
   │   ├─ Yes → keep count badges and pagination with the results area, not inside the search header.
   │   └─ No → only move them upward if the page is a compact summary view with a single result stream.
   └─ Does the sticky header hide too much of the list when scrolling?
       ├─ Yes → reduce padding, shorten the header band, or move secondary options into a collapse.
       └─ No → keep the current breakpoint ladder.
```

## Core Rules

- Treat the search input as the main action. It may be label-free when the placeholder is explicit and the input is visually large enough.
- Keep `sort_by` and `per_page` grouped together until the layout is truly narrow; do not let one drop to a new line while the other still looks like part of the same control set.
- Put low-frequency filters into one collapse section instead of spreading them across the top of the card.
- Treat `displayLevel` as a secondary density control: keep it in the search options collapse when the page is list-oriented, and only elevate it when density itself is the primary task.
- Keep result counts and pagination with the results area when the page can show multiple ledgers or cards; do not crowd the search header with navigation that belongs to reading the list.
- Use a short, distinctive header band if the card needs personality, but do not let the header become a multi-line hero that competes with the results list.
- For sticky headers, validate how much content is hidden below the fold after scroll. The acceptable threshold is part of the design decision, not an afterthought.
- Prefer Mary UI components and daisyUI semantic classes; avoid custom colors, arbitrary spacing, or decorative blocks that increase vertical height without adding meaning.

## Review Checklist

- [ ] The search input is visually dominant and understandable without a label.
- [ ] `sort_by` and `per_page` stay grouped at intermediate widths.
- [ ] `displayLevel` lives in the search options/collapse when the page is list-oriented.
- [ ] Result counts and page navigation stay in the results area when multiple ledgers/cards can appear.
- [ ] The search card does not push the results list too far below the fold.
- [ ] Secondary options are hidden behind one collapse.
- [ ] The breakpoint ladder is documented in `docs/work/*`.
- [ ] The scroll-occlusion threshold is documented in `docs/work/*`.

## Evidence

- `docs/work/ui-ux/ledger-list-redesign/2026-04-11_ledger-search-header-responsive-scroll-note.md`
- `docs/work/ui-ux/2026-04-11_ledger-index-manager-ui-plan.md`
- `resources/views/components/ledger/search.blade.php`
- `resources/views/livewire/ledger/index-manager.blade.php`
- `resources/views/livewire/ledger/records-table.blade.php`
- `resources/views/components/ledger/pagination-links.blade.php`

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-11
- recheck_after: 2026-07-11
- recheck_trigger: search header breakpoint ladder changes, sticky header overlap changes, or the results list starts feeling too hidden during scroll

