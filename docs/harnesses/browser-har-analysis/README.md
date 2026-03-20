# Browser HAR analysis harness

**用途:** Browser DevTools で取得した HAR ファイルを比較し、`document` / `livewire/update` / static assets のどれが支配的かを定型的に確認する。

## 置き場所の考え方

- ここは **copyable fixture / harness** の置き場
- 日々の手順は `docs/runbooks/browser-har-analysis-playbook.md` に置く
- 証跡や結論は `docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md` に残す

## 含まれるもの

- `scripts/har_summary.py`
  - HAR の要約
  - `livewire/update` の回数・サイズ・component breakdown
  - 複数 HAR の比較

## 最短コマンド

```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py storage/logs/localhost4.har storage/logs/localhost5.har
```

## 見方

- `document` が遅い → 初回 HTML / server-side rendering / layout / asset の可能性
- `livewire/update` が複数回・大きい → 同じ内容の再取得、イベント連鎖、payload 肥大の可能性
- `_debugbar` が大きい → debug noise と app cost を分けて評価する

## 関連

- [Browser HAR Analysis Skill](../../../.github/skills/browser-har-analysis/SKILL.md)
- [Browser HAR Analysis Playbook](../../../docs/runbooks/browser-har-analysis-playbook.md)
- [Evidence](../../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)

