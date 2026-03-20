# Browser HAR Analysis Playbook

**目的:** Browser DevTools で取得した HAR を比較し、`document` / `livewire/update` / static assets の支配要因を切り分ける。

## 使うタイミング

- `livewire/update` が複数回走っているように見える
- 初回 HTML が遅いのに、サーバー側ログでは軽く見える
- debugbar の影響を除いて純粋なアプリ遅延を見たい
- 同じ HAR 解析スクリプトを何度も書いている

## 手順

1. HAR を収集する
   - debug mode の有無を明記する
   - 同じページ遷移・同じ操作順で取得する
   - 比較対象を `localhost*.har` のように分かる名前で保存する

2. HAR を要約する
   - 共有スクリプトを使う:

```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py storage/logs/localhost4.har storage/logs/localhost5.har
```

3. 見るポイント
   - `document` の wait / size / TTFB
   - `livewire/update` の回数・サイズ・component breakdown
   - debugbar / `_boost` / Vite dev server などのノイズ
   - 2 回目以降の update が同じ内容を返していないか

4. まとめる
   - before / after の差分を数値で書く
   - 何が減ったか、何が残るかを分ける
   - issue か `docs/work/*` に evidence を残す

## 記録テンプレート

- HAR ファイル:
- debug mode:
- `document`:
- `livewire/update`:
- repeated components:
- static asset outliers:
- 結論:
- 次アクション:

## 関連

- [Browser HAR Analysis Skill](../../.github/skills/browser-har-analysis/SKILL.md)
- [Browser HAR Analysis Harness](../harnesses/browser-har-analysis/README.md)
- [Completion report / evidence](../work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)

