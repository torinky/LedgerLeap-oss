---
name: form-layout
description: Designs compact form layouts, field groups, labels, and helper text for LedgerLeap. Use when building or revising create/edit forms, settings panels, filter forms, or multi-section input surfaces.
compatibility: LedgerLeap (Mary UI, daisyUI v5, TailwindCSS v4, Livewire v3)
---

# form-layout

## Decision Tree

```text
Is this a form or field group?
├─ No → use another page or content skill.
└─ Yes
   ├─ Is it a search/list header or sticky filter strip?
   │   ├─ Yes → use `search-header-responsive-layout`.
   │   └─ No → continue here.
   ├─ Does the form have clearly separated sections?
   │   ├─ Yes → group fields into the smallest meaningful sections.
   │   └─ No → keep the form in one simple column or grid.
   ├─ Does any section need a title or short explanation?
   │   ├─ Yes → use a short noun title and short helper text.
   │   └─ No → keep the section label-only.
   ├─ Are labels, buttons, and helper text being written at the same time?
   │   ├─ Yes → use the translation workflow and keep text concise.
   │   └─ No → still avoid hardcoded UI copy.
   └─ Will the same field grouping repeat elsewhere?
       ├─ Yes → record the pattern in `docs/work/ui-ux/*`.
       └─ No → keep the implementation local.
```

## Core Rules

- Use Mary UI inputs, selects, textareas, toggles, buttons, and cards when the component exists.
- Use daisyUI semantic form classes when Mary UI does not cover the need.
- Keep labels as nouns or noun phrases.
- Keep helper text short and directly actionable.
- Keep buttons as actions.
- Put validation feedback near the field and keep it specific.
- Prefer a clear field order over clever arrangement.
- Group related fields, but do not over-nest sections.

## Practical layout guidance

- Use one-column forms when clarity matters more than density.
- Use responsive grid columns only when the field relationship is obvious and stable.
- Use section headers sparingly and only when the form truly has multiple conceptual blocks.
- Keep required indicators compact and consistent with the badge / indicator patterns already used in the repository.
- Keep long instructions out of the primary form body unless they are essential for completion.
- If a form is long, split it into logical blocks instead of trying to make one giant panel do everything.

## Text and interaction guidance

- Use translation keys for all user-facing copy.
- Keep submit and destructive actions clearly distinct.
- Do not rely on placeholder text as a label substitute.
- Use tooltips for overflow or optional explanation, not for primary instructions.
- If the form is part of a modal or drawer, keep the structure even simpler than a full-page form.

## Evidence and references

- Repo evidence: `docs/work/ui-ux/2026-04-18_design-workflow-reorganization-note.md`
- Related skill: `translation`
- Official references that shaped this pattern:
  - daisyUI semantic form and component classes
  - Material Design 3 form and density guidance
  - Apple HIG label guidance

## Freshness

- status: confirmed
- last_confirmed_at: 2026-04-18
- recheck_after: 90d
- recheck_trigger:
  - a repeated field-group pattern appears in more than one feature
  - form validation or label guidance changes upstream
  - Mary UI or daisyUI form components change in a way that affects layout

