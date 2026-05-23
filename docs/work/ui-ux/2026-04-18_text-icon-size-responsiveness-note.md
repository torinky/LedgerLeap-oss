# 2026-04-18 Text and Icon Size Responsiveness Note

## Goal

LedgerLeap の多くのページで見られる「小さすぎる文字サイズ」や「固定値のアイコンサイズ」を見直し、PC でも読みやすいサイズへ自然に変化する標準を作る。

## Evidence record

```yaml
claim: Primary text and meaningful icons should not be locked to one tiny fixed size across all devices; they should use readable defaults or responsive size steps so desktop layouts remain legible.
status: confirmed-official
last_confirmed_at: 2026-04-18
recheck_after: 90d
recheck_trigger:
  - pages again start using fixed tiny text/icon sizes for primary content
  - daisyUI or Mary UI size scale guidance changes upstream
  - Material 3 or Apple Dynamic Type guidance changes upstream
sources:
  - type: official-doc
    url: https://daisyui.com/docs/v5
  - type: official-doc
    url: https://m3.material.io/blog/material-density-web
  - type: official-doc
    url: https://m3.material.io/styles/typography/overview
  - type: official-doc
    url: https://developer.apple.com/design/human-interface-guidelines/typography
  - type: official-doc
    url: https://developer.apple.com/design/human-interface-guidelines/labels
  - type: repo-proof
    path: .github/instructions/design.instructions.md
  - type: repo-proof
    path: .github/skills/responsive-text-icon-sizing/SKILL.md
notes: daisyUI v5 documents a tokenized component size scale and larger xl size support; Material 3 recommends systematic density and type roles that adapt to device/context; Apple HIG says Dynamic Type should keep text and glyphs legible and that icons can scale alongside text.
```

## Working decision

### 1. Default rule

- Primary text should not be pinned to a tiny value across all devices.
- Meaningful icons should not be frozen at one small size when they are part of a readable control or heading.

### 2. When small sizes are acceptable

- Badges
- Dense table chrome
- Secondary metadata
- Decorative helper glyphs that are not the main focus

### 3. When responsive growth is preferred

- Titles
- Primary labels
- Main buttons
- Top-of-page context
- Important metadata that users scan on desktop

### 4. How to think about the rule

- Compactness should be intentional, not accidental.
- If a page is meant to be scanned on desktop, the size should support that.
- If multiple device sizes are supported, the sizing should adapt with the layout rather than staying frozen.

## External examples checked

- daisyUI v5: component size scales are tokenized and support larger variants.
- Material Design 3: density should be applied systematically, and type roles should adapt to device or context.
- Apple HIG: Dynamic Type should keep text and glyphs legible, and icons can scale with text.

## Next step

When updating a page, treat size as part of the design pattern instead of a one-off tweak. If the same readable sizing pattern repeats, promote it into a reusable skill rule rather than hard-coding it in the page.

