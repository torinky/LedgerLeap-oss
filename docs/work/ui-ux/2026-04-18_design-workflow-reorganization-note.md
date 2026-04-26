# 2026-04-18 Design Workflow Reorganization Note

## Goal

LedgerLeap のデザイン運用を、

- 新規ページを作るときの流れ
- 既存ページのデザインを見直す流れ
- 部品ごとの最小ルール

に分け、`design.instructions.md` を読みすぎない構成へ寄せる。

## Evidence record

```yaml
claim: LedgerLeap should keep a compact design baseline in design.instructions.md, route recurring page patterns to specialized skills, and record repeated component patterns in docs/work/ui-ux before promoting them to skills.
status: draft
last_confirmed_at: 2026-04-18
recheck_after: 90d
recheck_trigger:
  - a new page-shell or form pattern repeats in more than one feature
  - daisyUI component guidance changes upstream
  - the detail header, search header, or loading pattern changes again
sources:
  - type: official-doc
    url: https://daisyui.com/pages/tailwind-css-alternative
  - type: official-doc
    url: https://m3.material.io/components
  - type: official-doc
    url: https://github.com/carbon-design-system/carbon/blob/main/packages/react/src/components/Tag/Tag.mdx
  - type: official-doc
    url: https://www.gov.uk/guidance/content-design/writing-for-gov-uk
  - type: official-doc
    url: https://developer.apple.com/design/human-interface-guidelines/buttons
  - type: repo-proof
    path: .github/instructions/design.instructions.md
  - type: repo-proof
    path: .github/skills/ledger-detail-header/SKILL.md
  - type: repo-proof
    path: .github/skills/search-header-responsive-layout/SKILL.md
  - type: repo-proof
    path: .github/skills/livewire-loading-ui/SKILL.md
  - type: repo-proof
    path: docs/work/ui-ux/2026-04-18_ledger-detail-ui-redesign-retrospective.md
notes: daisyUI emphasizes semantic component classes such as btn, card, and badge. Material 3 treats buttons as actions, badges as counts/status, and chips as selection/filtering; Carbon keeps tags short and distinguishes read-only vs interactive usage; GOV.UK and Apple HIG both push short, clear, action-oriented titles and labels.
```

## Working decision

### 1. Top-level rule stays compact

`design.instructions.md` should only carry the always-on policy:

- Mary UI precedence
- daisyUI semantic classes
- translation-key-only text
- no arbitrary colors or spacing
- verification requirement for Blade changes

### 2. Existing skills handle the recurring patterns

- `title-block`: new page shells and compact top-of-page context blocks
- `form-layout`: create/edit forms, grouped fields, and helper text
- `ledger-detail-header`: detail page header / compact context block
- `search-header-responsive-layout`: sticky search/list headers
- `livewire-loading-ui`: loading tiers and mounted/overlay patterns
- `translation`: labels, buttons, descriptions, and errors

### 3. New page vs existing page should follow different first moves

#### New page
1. Choose the page role.
2. Build the shell.
3. Add the smallest useful title block.
4. Add one primary action and one clear empty/loading state.

#### Existing page
1. Identify the pain point.
2. Preserve behavior.
3. Tighten density and structure.
4. Record the new reusable pattern in `docs/work/ui-ux/*`.

## Promotion result

The following independent skills are now prepared for reuse:

- `title-block`
- `form-layout`

They are intentionally small first-pass skills so they can be loaded only when the page pattern actually needs them.

## External examples checked

- daisyUI: semantic component classes reduce class noise and keep HTML readable.
- Material Design 3: badges for status/counts, chips for interactive selection/filtering, buttons for actions.
- Carbon Design System: tags should be concise; selectable and dismissible variants exist for interactive use.
- GOV.UK: page titles should be short, clear, and active; writing should be user-focused.
- Apple HIG: button content should clearly communicate the action and stay concise.

## Next step

If a deeper sub-pattern appears inside `title-block` or `form-layout`, promote that sub-pattern into a follow-up skill and keep this note as the evidence trail.

