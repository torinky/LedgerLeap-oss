# 2026-04-11 Translation Skill and Sticky Action Bar Maintenance

## Goal

整理中の `translation` スキルと関連 UI 資産を、`skill-maintenance` のルールに沿って再配置する。

## Findings

- Ledger 翻訳の正本は `lang/ja/ledger.php`。
- 人が編集する単位は `lang/ja/ledger/*.php`。
- 生成物は `lang/ja.json`。
- 同期コマンドは `app/Console/Commands/CompareTranslations.php` の `translations:compare`。
- スティッキー・アクションバーのラベルは `ledger.action_bar_open` / `ledger.action_bar_close` を使う。

## What was reorganized

- `/.github/skills/translation/SKILL.md`
  - 長い手順とキーの詳細を参照資料へ分離。
  - 役割を「WHAT / WHEN / quick checks」に絞った。
- `/.github/skills/translation/references/key-routing.md`
  - キーの所有関係、dot notation、同期手順、鮮度メタデータを記録。
- `/.github/instructions/design.instructions.md`
  - 破損していた後半を整理し、翻訳の詳細は skill 側へ委譲。
  - sticky action bar の UI ルールだけを簡潔に残した。
- `resources/views/components/ledger/sticky-action-bar.blade.php`
  - 翻訳キーに基づくラベル表示を使用。

## Evidence

- `app/Console/Commands/CompareTranslations.php`
- `lang/ja/ledger.php`
- `lang/ja/ledger/ui.php`
- `lang/ja/ledger/workflow.php`
- `resources/views/components/ledger/sticky-action-bar.blade.php`
- `lang/ja.json`

## Notes

- `lang/ja.json` の `ledger.*` は手編集しない。
- 詳細なキー構造は `/.github/skills/translation/references/key-routing.md` を参照する。
