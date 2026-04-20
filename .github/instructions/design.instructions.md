# LedgerLeap Design & UI Generation Guidelines

This file is the compact top-level UI policy for generated or modified views and front-end components.

## 1. Primary Principles

- Build a clean, corporate administrative interface with good information density and strong readability.
- Use daisyUI v5 + Tailwind CSS v4 as the default UI foundation.
- Prefer Mary UI components whenever a matching component exists.
- Use daisyUI semantic component classes to keep markup readable: `btn`, `card`, `input`, `badge`, `collapse`, `tooltip`, `table`, `tabs`, `fieldset`, `join`, and related variants.
- Use Tailwind utilities to tune layout, spacing, and responsiveness; do not rebuild a component from scratch if daisyUI already provides it.
- Never use hardcoded hex colors, arbitrary pixel values, or custom CSS components unless there is no practical semantic alternative.
- Never hardcode natural-language UI text. Use translation keys such as `__('ledger.xxx')` and manage them through the translation workflow.
- Avoid locking primary text or meaningful icons to one tiny fixed size across all devices; prefer readable defaults or responsive size steps so desktop remains legible.

## 2. Color and Density Rules

- Use daisyUI semantic colors only: `primary`, `secondary`, `accent`, `info`, `success`, `warning`, `error`, `base-100`, `base-200`, `base-300`, and `base-content`.
- Prefer tokens and semantic variants over arbitrary height or color tuning.
- Keep default component sizing unless the page pattern explicitly requires a compact or dense variant.
- Avoid custom visual chrome that competes with the content.

## 3. Routing: pick the smallest relevant rule set first

Before adding new markup, decide whether the page is a new surface or a revision of an existing one.

### New page

- Start from the page shell: title block, main content region, primary action, and empty/loading/error states.
- Choose the page pattern first, then load only the matching helper skill(s).
- Keep the initial version small and readable; add detail progressively.

### Existing page review

- Start from the current pain point: layout density, scroll occlusion, action placement, copy clarity, or loading behavior.
- Preserve behavior first, then tighten layout and component usage.
- Document the before/after decision in `docs/work/ui-ux/*` when the change reveals a reusable pattern.
- For split-pane list/detail surfaces, use Mary UI cards as the outer shell for each pane when the component exists, and place pane controls in the card title or menu slot instead of free-floating wrappers.
- Keep pane labels, headings, and metadata in translation keys so the same surface does not mix semantic components with hardcoded copy.

### Reuse existing skills before inventing new guidance

- Detail header and compact context block: [`ledger-detail-header`](../skills/ledger-detail-header/SKILL.md)
- Search / list sticky header and breakpoint behavior: [`search-header-responsive-layout`](../skills/search-header-responsive-layout/SKILL.md)
- Loading tiers, `wire:loading`, `x-show`, and sticky UI interactions: [`livewire-loading-ui`](../skills/livewire-loading-ui/SKILL.md)
- UI copy, labels, and error text: [`translation`](../skills/translation/SKILL.md)
- Text and icon sizing / legibility: [`responsive-text-icon-sizing`](../skills/responsive-text-icon-sizing/SKILL.md)

## 4. Page structure rules

- Title blocks should be compact, context-rich, and avoid unnecessary vertical height.
- Use breadcrumbs, version labels, and compact metadata only when they add immediate value.
- Long guidance belongs in collapsible sections, not in the first viewport.
- Forms should group related fields, keep labels as nouns, and keep helper text short.
- When a form or page shell pattern repeats, record the pattern in `docs/work/ui-ux/*` before promoting it into a reusable skill.
- For a reusable title block pattern, use the `title-block` skill.
- For reusable form layout and field grouping patterns, use the `form-layout` skill.

## 5. Component selection hierarchy

1. Mary UI component if it exists.
2. daisyUI semantic class if it expresses the role clearly.
3. Tailwind utility adjustment if layout or spacing needs tuning.
4. Custom CSS only if the first three options cannot express the need safely.

## 6. Text and icon sizing

- Primary text should stay readable on desktop; do not freeze it at a tiny value for every device.
- Meaningful icons should scale with the text or the control role instead of staying at one fixed small size.
- If a Mary icon is already legible at its default size, prefer the component default over repeating a fixed `w-* h-*` pair, especially for helper/tooltip icons and metadata markers.
- Use small sizes only for badges, dense chrome, or secondary metadata.
- Prefer responsive size steps or component defaults when the same page must work on both mobile and desktop.
- Use the `responsive-text-icon-sizing` skill when this becomes a repeated pattern.

## 7. Common component guidance

- Buttons should look like actions. Use concise labels and prefer verb phrases.
- Inputs should remain readable and accessible; use labels rather than relying on placeholders.
- Cards should be used for grouped surfaces, not as a default wrapper for every block.
- Badges should express short state, count, or metadata.
- Chips / tags are for selectable or dismissible labels.
- Tooltips should carry overflow details, not essential primary text.
- If a badge or state marker is icon-only, pair it with a tooltip and sr-only text so the meaning remains accessible.

## 8. Responsive and visual behavior

- Assume tablet-first and office laptop use cases.
- Keep touch targets usable on smaller devices.
- Use `lg:` for denser list/table layouts and wider screens.
- For dense comparison tables, keep row hover states obvious enough to read against the card surface; avoid faint hover overlays that disappear into the background.
- Allow wrapping or horizontal scrolling where long inline structures would otherwise break the layout.
- Avoid invisible-only hover affordances for critical actions.

## 9. Component collision and interaction rules

- Watch for absolute-positioned framework helpers such as collapse arrows, badges, or icons that can overlap content.
- If a component already exposes an attribute for a label or icon, prefer the attribute over extra wrapper spans.
- Keep rotation or animation on wrapper elements when a component needs transform-based motion.
- Use `x-collapse` for expandable content when the visual transition matters.
- Do not wrap a modern collapsible layer around an older “more” component that creates duplicate interaction paths.
- Scope Livewire loading states with `wire:target` so cards and panes do not appear to reload for unrelated actions.

## 10. UI verification

- If you change Blade structure, verify both the visuals and the underlying variable scope / route resolution.
- Run the relevant Feature test or rendering check for the affected page.
- Report the verification result together with the change.

## 11. Evidence and maintenance

- If a pattern is reusable across more than one page, capture it in `docs/work/ui-ux/*` first.
- If it becomes a durable capability, promote it to a skill through the maintenance workflow.
- Keep this file compact; move long examples and deep procedures into skills, references, or work notes.

## 12. External examples that informed this baseline

- daisyUI: semantic component classes like `btn`, `card`, and `badge` keep HTML readable.
- Material Design 3: buttons prompt actions; badges show counts or status; chips are for selection / filtering; density should come from component guidance, not arbitrary heights.
- Carbon Design System: tags should be short; read-only tags categorize, while selectable and dismissible tags support interaction.
- GOV.UK content design: titles should be short, clear, and active; web writing should be specific and user-focused.
- Apple HIG: button labels should clearly communicate the action and stay concise.

See `docs/work/ui-ux/2026-04-18_design-workflow-reorganization-note.md` for the reorganization evidence and the first-pass routing rationale.
