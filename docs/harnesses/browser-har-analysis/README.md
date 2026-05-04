# Browser HAR analysis harness

**用途:** Browser DevTools で取得した HAR ファイルを比較し、`document` / `livewire/update` / static assets のどれが支配的かを定型的に確認する。

## 置き場所の考え方

- ここは **copyable fixture / harness** の置き場
- 日々の手順は `docs/runbooks/browser-har-analysis-playbook.md` に置く
- 証跡や結論は `docs/work/ui-ux/` 以下に残す

## 含まれるもの

- `scripts/har_summary.py`
  - HAR の要約（top slow requests, total request count）
  - ⚠️ **`livewire-HASH/update` URL にマッチしない既知の問題あり**（→ `har_lazy_analysis.py` を使うこと）

- `scripts/har_lazy_analysis.py`（推奨）
  - `livewire-HASH/update` URL パターンを正しく検出
  - Folder-switch シーケンスの分離有無（Lazy フラグ）を自動判定
  - **interactive time**（IndexManager 応答 = スケルトン表示）と **content-complete time**（RecordsTable 本体完了）を分離して計測
  - 複数 HAR ファイルの before/after 比較

## 最短コマンド

```bash
# 推奨: Lazy 分析・before/after 比較
python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
    localhost.har localhost2.har localhost3.har

# 旧来の基本サマリー（livewire count は不正確）
python3 docs/harnesses/browser-har-analysis/scripts/har_summary.py localhost.har localhost2.har
```

## ⚠️ URL パターン注意

LedgerLeap の Livewire エンドポイントは `livewire-HASH/update` 形式（例: `livewire-4acf5bca/update`）。
`har_summary.py` は `'livewire/update' in url` でマッチしているため count に 0 が返る。
`har_lazy_analysis.py` は `re.search(r'livewire[^/]*/update', url)` で正しく検出する。

## 見方

- `document` が遅い → 初回 HTML / server-side rendering / layout / asset の可能性
- `livewire/update` が複数回・大きい → 同じ内容の再取得、イベント連鎖、payload 肥大の可能性
- `_debugbar` が大きい → debug noise と app cost を分けて評価する
- **#[Lazy] 後の Folder-switch**: IM+placeholder bundle (~164KB, ~790ms) + RT 単独 (460〜860KB, 5〜13s) の 2 本構成が正常

## 関連

- [Browser HAR Analysis Skill](../../../.github/skills/browser-har-analysis/SKILL.md)
- [Browser HAR Analysis Playbook](../../../docs/runbooks/browser-har-analysis-playbook.md)
- [Evidence (Livewire update dedup)](../../../docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md)
- [Evidence (Issue #194 Lazy effect)](../../../docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md)
