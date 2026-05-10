---
name: mary-ui-component-patterns
description: Standard patterns for using Mary UI components (x-mary-card, x-mary-modal, x-mary-header) in LedgerLeap Blade views. Use when building new screens or refactoring existing views.
compatibility: LedgerLeap (Mary UI, daisyUI, TailwindCSS)
---

# mary-ui-component-patterns

## Component Selection Hierarchy

1. **Mary UI component** if it exists.
2. **daisyUI semantic class** if it expresses the role clearly.
3. **Tailwind utility adjustment** if layout or spacing needs tuning.
4. **Custom CSS only** if the first three options cannot express the need safely.

## Card (`x-mary-card`)

Key attributes: `separator`, `shadow`, `body-class`, `class`.
Key slots: `title`, `menu`, `actions`, default.

Anti-patterns:
- Do not build card titles with `<h2 class="card-title ...">`; use `<x-slot:title>` for consistent Mary UI spacing/fonts.
- Do not wrap body with `<div class="card-body p-6">`; use `body-class` attribute.
- Do not add excessive decoration (`uppercase tracking-tighter font-black`) to card titles. Rely on Mary UI defaults.

## Modal (`x-mary-modal`)

Key attributes: `id`, `title`, `subtitle`, `separator`, `box-class`, `persistent`.
Key slots: default (body), `actions`.

Anti-patterns:
- Do not build modals with DaisyUI `.modal` + hidden checkbox. Use Mary UI for accessibility and backdrop handling.
- Do not place form actions outside the `actions` slot.
- Do not use `<label for="id">` to close; Mary UI uses native `<dialog>` → call `.close()`.

## Header (`x-mary-header`)

Key attributes: `:title`, `subtitle`, `size`, `separator`, `progress-indicator`, `icon`.
Key slot: `actions`.

## Full Code Examples

See [references/card-modal-header-examples.md](references/card-modal-header-examples.md) for copyable Blade snippets.

## Evidence & Maintenance

- Extracted from `ledgerDefine/edit.blade.php` refactoring (Issue #208).
- Mary UI component API: `vendor/robsontenorio/mary/src/View/Components/`.
- When a new Mary UI component pattern is established, add it to this skill.
