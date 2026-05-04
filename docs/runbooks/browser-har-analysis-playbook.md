# Browser HAR Analysis Playbook

**目的:** Browser DevTools で取得した HAR を比較し、`document` / `livewire/update` / static assets の支配要因を切り分ける。

## 使うタイミング

- `livewire/update` が複数回走っているように見える
- 初回 HTML が遅いのに、サーバー側ログでは軽く見える
- debugbar の影響を除いて純粋なアプリ遅延を見たい
- `#[Lazy]` 導入前後の interactive time / content-complete time を比較したい
- 同じ HAR 解析スクリプトを何度も書いている

## 手順

1. HAR を収集する
   - debug mode の有無を明記する
   - 同じページ遷移・同じ操作順で取得する
   - 比較対象を `localhost*.har` のように分かる名前で保存する

2. HAR を要約する（推奨: `har_lazy_analysis.py`）

```bash
# #[Lazy] 対応・before/after 比較（推奨）
python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
    localhost.har localhost2.har localhost3.har

# 旧来の基本サマリー（livewire count が 0 になる既知の問題あり）
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py localhost.har localhost2.har
```

> ⚠️ **`har_summary.py` で livewire/update count が 0 の場合** は URL パターン不一致（`livewire-HASH/update` 形式）。
> `har_lazy_analysis.py` を使うこと。

3. 見るポイント
   - `document` の wait / size / TTFB
   - `livewire/update` の回数・サイズ・component breakdown
   - **#[Lazy] 導入後: Folder-switch sequences の lazy フラグ・IM time・RT time**
     - `lazy=✅`, `IM_med ~790ms` → interactive time 改善を確認
     - RT standalone が 5〜13s → 方針B（Blade ループ最適化）の残課題
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
- **interactive time (IM med)**:
- **content-complete time (IM + RT)**:
- 結論:
- 次アクション:

## 関連

- [Browser HAR Analysis Skill](../../.github/skills/browser-har-analysis/SKILL.md)
- [Browser HAR Analysis Harness](../harnesses/browser-har-analysis/README.md)
- [Evidence (Livewire update dedup)](../work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)
- [Evidence (Issue #194 Lazy effect)](../work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md)
