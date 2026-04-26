---
description: Analyze browser HAR files, compare repeated requests, and standardize recurring inspection commands.
---

# browser-har-analysis

## Goal

Browser DevTools で取得した HAR ファイルを比較し、`document` / `livewire/update` / static assets のどれが支配的かを切り分ける。重複して使う解析スクリプトは、ハーネスに定型化したものを使う。

参照:
- [Browser HAR Analysis Skill](../skills/browser-har-analysis/SKILL.md)
- [Browser HAR Analysis Playbook](../../docs/runbooks/browser-har-analysis-playbook.md)
- [Browser HAR Analysis Harness](../../docs/harnesses/browser-har-analysis/README.md)
- [Completion report / evidence](../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)

## Inputs

- 比較したい HAR ファイル（例: `storage/logs/localhost4.har` と `storage/logs/localhost5.har`）
- debug mode の有無
- 観測したい観点（初回 HTML / Livewire payload / asset / repeated request）

## Recommended Flow

1. HAR を 1 つまたは複数指定する
2. ハーネスの共有スクリプトで要約を作る
3. `document` / `livewire/update` / assets を分けて比較する
4. repeated request が同じコンポーネント群を返していないか確認する
5. 結果を issue / docs/work に evidence 付きで残す

## Deliverable Format

- Capture context
- Top-level metrics
- `livewire/update` の回数 / サイズ / component breakdown
- before / after comparison
- next action

## Guardrails

- debugbar などのノイズは app cost と分けて記録する
- 1 回目と 2 回目の Livewire update が同じ内容を返していないか確認する
- 同じスクリプトを何度も書かず、ハーネスの定型コマンドを優先する

