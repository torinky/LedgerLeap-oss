# 2026-04-11 Status / Count Display Badge Guidance

## Goal

フッターの文書ステータス表示をきっかけに、LedgerLeap では「短い状態・件数・メタ情報」を単なるテキストではなく、`badge` を中心に再評価する方針を明文化する。

## Evidence record

```yaml
claim: Short status / count / metadata display should be badge-first, while selectable/filterable labels should be treated as chip/tag-like interactions
status: confirmed-official
last_confirmed_at: 2026-04-11
recheck_after: 90d
recheck_trigger:
  - upstream design-system guidance changes
  - LedgerLeap UI copy / badge patterns change in the same area
sources:
  - type: official-doc
    url: https://m3.material.io/components/badges/overview
  - type: official-doc
    url: https://m3.material.io/components/chips/overview
  - type: official-doc
    url: https://carbondesignsystem.com/components/tag/usage/
  - type: repo-proof
    path: resources/views/components/ledger/sticky-action-bar.blade.php
  - type: repo-proof
    path: .github/instructions/design.instructions.md
notes: Material Badge describes counts/status information; Material Chips describe selectable/filterable actions; Carbon Tags emphasize concise labels and tooltip disclosure for overflow.
```

## Working decision

- **badge**: 1〜数語で読める短い状態名、件数、ラベル、メタ情報。非操作で、一覧・フッター・サマリーで一目判別したいもの。
- **chip / tag**: フィルタ、選択、解除、dismiss など、ユーザーが操作するラベル。
- **text**: 長文、説明文、エラー文、操作案内など、読み物として意味を持つもの。

## Practical heuristics

1. 状態を「読む」だけなら badge を優先する。
2. 補助説明は tooltip に逃がせるなら badge に寄せる。
3. 状態に icon を添えられるなら、視認性のために icon + badge を検討する。
4. selectable / dismissible / filterable なラベルは badge より chip / tag の方が自然。
5. 長い文を badge に押し込まない。文章は text のまま残す。
6. フッターや sticky action bar のような視線が集まる箇所は、status / count / summary を badge-first で見直す。

## External references checked on 2026-04-11

### Material Design 3

- Badge: https://m3.material.io/components/badges/overview
  - page description: badges show notifications, counts, or status information on navigation items and icons; a badge can include labels or numbers.
- Chips: https://m3.material.io/components/chips/overview
  - page description: chips help people enter information, make selections, filter content, or trigger actions.

### Context7 check (Material Design 3)

- Library ID: `/websites/m3_material_io`
- Context7 extraction:
  - UX writing and information design make UIs easy to use.
  - Better writing leads to better experiences in common UI patterns such as notifications, labels, errors, and localization.
  - Be concise; concise content is fundamental to usability.

### Carbon Design System

- Tag usage: https://carbondesignsystem.com/components/tag/usage/
  - tags are used to label, categorize, or organize items.
  - titles should be concise and informative.
  - overflow content should disclose the full title in a tooltip.
  - selectable / dismissible / operational variants exist for interactive use cases.

### Context7 check (Carbon Design System)

- Library ID: `/carbon-design-system/carbon`
- Context7 extraction:
  - Tags can categorize items and should use short labels for easy scanning.
  - Use two words only if necessary to describe the status and differentiate it from other tags.
  - Read-only tags are for labeling/categorizing and do not have interactive functionality.
  - Selectable tags are for selecting/unselecting items or filtering by label.
  - Dismissible tags are for removing filters.
  - Operational tags are for disclosing related items, not for external navigation.

## How this maps to LedgerLeap

- **Footer / summary status**: badge + icon + tooltip.
- **Counts / short metadata**: badge if scanning value is important.
- **Interactive filters**: chip/tag-like treatment rather than plain badge.
- **Long descriptions**: keep as text.

## Evidence links

- Repo example: `resources/views/components/ledger/sticky-action-bar.blade.php`
- Design rule: `/.github/instructions/design.instructions.md`
- Implementation note: `docs/work/localization/2026-04-11_translation-skill-and-sticky-action-bar-maintenance.md`

