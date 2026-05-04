# localhost4.har × パフォーマンスログ 照合レポート

**作成日**: 2026-05-04  
**対象 Issue**: [#202](https://github.com/torinky/LedgerLeap/issues/202)  
**HAR**: `localhost4.har` (2026-05-04 11:28〜11:30 取得)  
**ログ**: `storage/logs/laravel-2026-05-04.log` (11:41 前後の `column_html_show_ms` エントリ)  
**分析ツール**:
- `docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py`
- `docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py` (新規)

---

## 1. HAR サマリー

```
[localhost4.har]  livewire/update: 21 requests

file           lw_reqs  max_time   med_time   max_body
localhost4.har      21   10307ms    4341ms(??)  640164B
```

### フォルダ切替シーケンス（6件）

| lazy | IM_time | IM_body | RT_time | RT_body | interactive | content |
|------|---------|---------|---------|---------|-------------|---------|
| ✅ | 848ms | 265KB | (RT未確認) | — | 848ms | — |
| ✅ | 628ms | 163KB | **10307ms** | 461KB | **628ms** | 10935ms |
| ✅ | 924ms | 172KB | 3759ms | 199KB | **924ms** | 4683ms |
| ❌ | **9558ms** | 640KB | (IM と同一) | — | 9558ms | 9558ms |
| ❌ | **7073ms** | 581KB | (IM と同一) | — | 7073ms | 7073ms |
| ❌ | 2362ms | 442KB | (IM と同一) | — | 2362ms | 2362ms |

- **Lazy ✅ 件数**: 3/6 (50%) — localhost3.har (100%) から **後退**
- ❌ の 3 件はすべて `index-manager + records-table + N×records-table-row` が 1 バンドルになっており、Lazy 分離が機能していない

### Top-5 最遅リクエスト

| time_ms | wait_ms | body | components |
|---------|---------|------|-----------|
| 10307ms | 10295ms | 461KB | ledger.records-table |
| 9558ms | 9531ms | 640KB | IM + RT + 5×Row |
| 7073ms | 7060ms | 581KB | IM + RT + 18×Row |
| 4341ms | 4328ms | 199KB | ledger.records-table |
| 3759ms | 3751ms | 199KB | ledger.records-table |

→ **wait ≈ total** のため、全遅延はサーバサイド PHP 処理によるもの。ネットワーク遅延は無視できる。

---

## 2. パフォーマンスログ集計

### column_html_show_ms by render_kind（合計 1,483 エントリ / 80,000ms）

| type | count | sum | mean | median | max |
|------|------:|----:|-----:|-------:|----:|
| **textarea** | 397 | **72,264ms** | 182ms | 155ms | 2,695ms |
| auto_number | 222 | 4,487ms | 20ms | 5.8ms | 2,123ms |
| text | 238 | 1,386ms | 5.8ms | 4.0ms | 79ms |
| YMD | 222 | 1,267ms | 5.7ms | 3.9ms | 49ms |
| number | 40 | 417ms | 10ms | 6.8ms | 42ms |
| select | 354 | 150ms | 0.4ms | 0.4ms | 6ms |
| chk | 10 | 1ms | 0.1ms | 0.1ms | 0.2ms |

**→ `textarea` が全体 90% (72,264ms / 79,972ms) を占める**

### 異常スパイク上位（>20ms, 338 エントリ）

全 338 件のうち、約 330 件が `textarea` タイプ。  
残り数件は `auto_number` の散発スパイク（ledger_id=2, col_id=8: 最大 2,123ms）。

---

## 3. ボトルネック特定

### 主ボトルネック: `textarea` → `MarkdownRenderer` + `AutoLinkService`

`ColumnHtmlService::show()` の textarea 経路:
```
markdownRenderer->toHtml()       ← Markdown → HTML 変換
autoLinkService->convert()       ← 自動リンク変換（正規表現 DOM 走査）
htmlProcessorService->processTextNodes()  ← highlight があれば DOMDocument 操作
```
これをセルごとに直列実行しており、1 セル 100〜500ms が大量の textarea 列を持つ台帳で積み重なる。

**推計検証**:
- ❌ non-lazy バンドル `581KB / 7073ms / 20 components` = IM + RT + **18 RecordsTableRow**
- 18 行 × 2 textarea列 × ~155ms(中央値) ≈ **5,580ms** → HAR の 7,073ms に近い
- IM + RT の初期コスト ~500ms + rows の待ち重複を加えると整合

### 副ボトルネック: `auto_number` の散発スパイク

- ledger_id=2, col_id=8 で繰り返し 21〜2,123ms の幅広い分布
- `auto_number` 経路は `autoLinkService->convert()` を呼ぶため、AutoLink パターンが当該 ledger で多数マッチしている可能性

### Lazy 回帰（構造問題）

localhost3.har では `lazy%=100%` だったのに、localhost4.har では `lazy%=50%` に後退。  
`❌` の 3 件は IM + RT + Row が同一バッチになっており、`#[Lazy]` が条件付きで無効化された疑いがある。  
→ RecordsTableRow の `#[Lazy(isolate: false)]` が RecordsTable 再描画パスで解除されている可能性を調査要

---

## 4. 優先アクション

### 優先度 1 — textarea レンダリングのキャッシュ (最大インパクト)
- `MarkdownRenderer::toHtml()` の出力を `ledger_id + column_id + updated_at` キーでキャッシュ
- 同一 ledger の再描画では PHP 処理がほぼゼロになり、10s → 1s 台への改善が期待できる
- 関連: Issue #200 (状態ベースキャッシュ)

### 優先度 2 — Lazy 回帰の原因調査
- localhost4.har の ❌ バンドルが発生する操作パスを特定
- `RecordsTable` / `RecordsTableRow` の `#[Lazy]` が外れるトリガーを確認
- localhost3.har との操作差分（どのフォルダ、どの順序で切り替えたか）を確認

### 優先度 3 — auto_number の AutoLink コスト測定
- ledger_id=2, col_id=8 の `auto_number` が 2,123ms になるケースの URL パターン数を確認
- 台帳内 URL が多い場合、AutoLink の正規表現を最適化または短絡評価を検討

---

## 5. 新規スクリプト

```bash
# パフォーマンスログ集計
python3 docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py \
    storage/logs/laravel-2026-05-04.log

# HAR 分析
python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
    localhost4.har
```

---

## 6. 参照

- Issue: [#202](https://github.com/torinky/LedgerLeap/issues/202)
- 前回計測: `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md`
- 前回計画: `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-202-performance-hotspot-reassessment-draft.md`
- analyze_perf_log.py: `docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py`

