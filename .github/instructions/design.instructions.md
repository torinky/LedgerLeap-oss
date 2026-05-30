# LedgerLeap Design & UI Generation Guidelines

This file is the compact top-level UI policy for generated or modified views and front-end components.

## 1. Primary Principles

- Build a clean, corporate administrative interface with good information density and strong readability.
- Use daisyUI v5 + Tailwind CSS v4 as the default UI foundation.
- Prefer Mary UI components whenever a matching component exists.
- When using a Mary UI component, first check the component's supported parameters and attributes, then use those built-in options instead of adding custom wrapper markup for labels, titles, icons, or state text.
- Use daisyUI semantic component classes to keep markup readable: `btn`, `card`, `input`, `badge`, `collapse`, `tooltip`, `table`, `tabs`, `fieldset`, `join`, and related variants.
- Use Tailwind utilities to tune layout, spacing, and responsiveness; do not rebuild a component from scratch if daisyUI already provides it.
- Never use hardcoded hex colors, arbitrary pixel values, or custom CSS components unless there is no practical semantic alternative.
- Never hardcode natural-language UI text. Use translation keys such as `__('ledger.xxx')` and manage them through the translation workflow.
- Avoid locking primary text or meaningful icons to one tiny fixed size across all devices; prefer readable defaults or responsive size steps so desktop remains legible.
- For wide business dashboards and tabbed summary pages, widen the outer container first on desktop (`w-full` plus a larger `max-w-*`) and keep the internal control groups centered; do not compensate with scattered child spacing that makes badges or tabs drift toward both edges.

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
- For overview cards that summarize affiliation or role, prefer a stats-based body: keep the card title compact, then render the primary affiliation / role / status values as Mary UI stats so the hierarchy is obvious at a glance.
- For those summary cards, treat the card title as a compact context header and use the card title slot for a short icon + text row when the section needs a visual anchor.

### Reuse existing skills before inventing new guidance

- Detail header and compact context block: [`ledger-detail-header`](../skills/ledger-detail-header/SKILL.md)
- Search / list sticky header and breakpoint behavior: [`search-header-responsive-layout`](../skills/search-header-responsive-layout/SKILL.md)
- Tabbed dashboards, notification surfaces, and count badge placement: [`tabbed-dashboard-responsive-layout`](../skills/tabbed-dashboard-responsive-layout/SKILL.md)
- Loading tiers, `wire:loading`, `x-show`, and sticky UI interactions: [`livewire-loading-ui`](../skills/livewire-loading-ui/SKILL.md)
- UI copy, labels, and error text: [`translation`](../skills/translation/SKILL.md)
- Text and icon sizing / legibility: [`responsive-text-icon-sizing`](../skills/responsive-text-icon-sizing/SKILL.md)

## 4. Page structure rules

- Title blocks should be compact, context-rich, and avoid unnecessary vertical height.
- Use breadcrumbs, version labels, and compact metadata only when they add immediate value.
- Long guidance belongs in collapsible sections, not in the first viewport.
- Forms should group related fields, keep labels as nouns, and keep helper text short.
- For compact search/list headers, split the surface into primary query controls and a secondary summary strip. Keep the summary strip badge-first and iconized so collapsed filters still expose active sort, display level, semantic-search, synonym, technical-term, and workflow status.
- For compact filter rows, use a noun label plus a small semantic icon and a tooltip hint; do not rely on placeholders or hidden text for the primary meaning.
- When the compact row contains a toggle, keep the row wrapper as a `label` so the whole row remains clickable and the text/control association stays explicit.
- When a form or page shell pattern repeats, record the pattern in `docs/work/ui-ux/*` before promoting it into a reusable skill.
- For a reusable title block pattern, use the `title-block` skill.
- For reusable form layout and field grouping patterns, use the `form-layout` skill.
- For Mary UI Card / Modal / Header usage patterns, use the `mary-ui-component-patterns` skill.
- For persistent ledger footers and bottom action surfaces, prefer `x-ledger.sticky-action-bar` over custom fixed cards or ad hoc bottom bars, and follow `sticky-action-bar-footer-pattern` for slot responsibilities and badge-first summaries.
- Use the shared footer shell consistently: `left` for escape / navigation / secondary actions, `right` for primary or workflow actions, and `footer` for short status or count summaries.
- Keep footer summaries badge-first and compact; place the longer explanation in a tooltip or nearby helper text when needed.
- Match sibling ledger screens when adjusting footer density, mobile pull-up behavior, and z-index layering so the footer does not occlude the main content.
- Badge-first review is not limited to detail pages; apply the same status / count / metadata check to list rows, cards, forms, title blocks, and any other surface that presents compact state.
- When a screen includes badges or other compact status markers, verify the nearby action area and tooltip wording together so the meaning is clear without adding a second text label.
- For permission / access summary cards, surface the subject and viewer in the overview before the drill-down list so the user can answer "whose access to what" without reading the rows first.
- For role / affiliation summary cards, show the role and organization as separate stats rather than inline text blobs; keep the labels short, the values prominent, and the descriptive note secondary.
- If a direct / inherited marker is too long for the card, reduce it to icon-only + tooltip + sr-only text instead of keeping a verbose inline label.
- For inherited access rows, render the granting folder as a breadcrumb-style path with folder icons and separators so the hierarchy break is visually obvious.

## 5. Component selection hierarchy

1. Mary UI component if it exists.
2. daisyUI semantic class if it expresses the role clearly.
3. Tailwind utility adjustment if layout or spacing needs tuning.
4. Custom CSS only if the first three options cannot express the need safely.

## 6. Text and icon sizing

- Primary text should stay readable on desktop; do not freeze it at a tiny value for every device.
- Meaningful icons should scale with the text or the control role instead of staying at one fixed small size.
- If a Mary icon is already legible at its default size, prefer the component default over repeating a fixed `w-* h-*` pair, especially for helper/tooltip icons and metadata markers.
- Prefer Mary icon attributes such as `label`, `title`, and built-in size or variant options when they exist, instead of recreating those behaviors with custom spans or repeated class overrides.
- Use small sizes only for badges, dense chrome, or secondary metadata.
- Prefer responsive size steps or component defaults when the same page must work on both mobile and desktop.
- Use the `responsive-text-icon-sizing` skill when this becomes a repeated pattern.

## 6.1 Stats card sizing

- In `stats` / `stat` layouts, let the value carry the visual weight and keep the title/description smaller but still readable.
- Do not collapse role or affiliation values into tiny metadata text just because the block is inside a card; the summary value is primary content.
- If the stats are used as the main summary surface, keep the spacing deliberate so each stat reads as a separate unit and not as a dense paragraph.

## 7. Common component guidance

- Buttons should look like actions. Use concise labels and prefer verb phrases.
- Inputs should remain readable and accessible; use labels rather than relying on placeholders.
- Cards should be used for grouped surfaces, not as a default wrapper for every block.
- Badges should express short state, count, or metadata.
- If a badge is an active filter or state marker inside a collapsed header, keep the text label visible and pair it with a tooltip; reserve icon-only treatment for secondary markers.
- For dense toggle rows inside cards or collapsible filters, prefer a `label` wrapper over a generic `div` so the touch target covers the row and the control remains accessible.
- Chips / tags are for selectable or dismissible labels.
- Tooltips should carry overflow details, not essential primary text.
- If a badge or state marker is icon-only, pair it with a tooltip and sr-only text so the meaning remains accessible.
- In dense tables, keep the primary entity label visible in the row cell; use icon-only badges for secondary state markers, not for the main role / user / organization name.
- If the compact treatment hides the entity name itself, it has gone too far; restore the text label and reserve icon-only treatment for the accompanying state.

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
- If you change a tabbed dashboard or notification surface, use Mary UI tabs when available, keep tab badges in one place, and verify the workflow too: the controller's initial active tab, any Livewire count events, and the presence of the relevant tab content all need to stay in sync with the new layout.
- Run the relevant Feature test or rendering check for the affected page.
- Report the verification result together with the change.

## 10.1 Frontend error detection via browser.log

1. **ユーザーに画面操作を促す**: Blade/JS/CSS 変更後、以下の文言でユーザーにブラウザでの確認を依頼する:
   ```
   フロントエンドの変更を適用しました。以下の画面をブラウザで開いて操作し、browser.log へのエラー出力を確認します:
   - [変更した画面のURL]
   - 操作内容: [グループ開閉 / フォーム送信 / ページ遷移 など]
   
   操作後に「完了」とお知らせください。
   ```
2. **browser.log を確認**: ユーザーの操作完了後、以下を実行:
   ```bash
   grep -i -E "Alpine Expression Error|ReferenceError|TypeError|Script error" storage/logs/browser.log | tail -30
   ```
3. Key error patterns and their typical causes:
   - `Alpine Expression Error: Can't find variable: X` → X is undefined at `x-data` evaluation time. Check script loading order: `@stack('scripts')` must be before `@livewireScriptConfig`.
   - `Script error.` (repeated) → cascading failure from an earlier script error. Trace to the first error in the log.
   - `ReferenceError` / `TypeError` → missing JS function or property access on undefined. Verify module imports and global function registration.
4. Backend tests passing does NOT guarantee frontend is healthy — a misordered `<script>` tag or missing Alpine registration can silently break all interactivity.
5. If browser.log errors are present, fix the frontend loading/integration issue first before considering backend changes.
6. Clear `storage/logs/browser.log` after verifying fixes so subsequent runs have a clean baseline.

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
