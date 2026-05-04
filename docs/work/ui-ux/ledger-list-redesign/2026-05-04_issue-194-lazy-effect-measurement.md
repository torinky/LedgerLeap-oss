# Issue #194 方針A (#[Lazy]) 効果測定レポート

**作成日**: 2026-05-04  
**対象 Issue**: [#194](https://github.com/torinky/LedgerLeap/issues/194)  
**HAR**: `localhost.har` / `localhost2.har` / `localhost3.har`  
**測定ツール**: `docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py`

---

## 1. 測定文脈

| ファイル | 状態 | debug |
|---------|------|-------|
| `localhost.har` | Sprint 0 基準: refreshChildren カスケードあり、Lazy なし | ON |
| `localhost2.har` | Sprint 4.1: refreshChildren カスケード除去済み、Lazy なし | ON |
| `localhost3.har` | 方針A 実装後: `RecordsTable` に `#[Lazy(isolate: false)]` 付与 | ON |

---

## 2. 比較サマリー

```
file                        lw_reqs   max_time   med_time   max_body
  localhost.har                  89     14892ms      1101ms     953321B
  localhost2.har                  8     15740ms      6269ms     895201B
  localhost3.har                 14     12867ms       506ms     860803B
```

### フォルダ切替 interactive time（IndexManager 応答 = ユーザーが最初に UI 反応を得るまで）

```
file                       sequences  lazy%   IM_min   IM_med   IM_max   RT_med
  localhost.har                    7     0%   5416ms   6576ms  14892ms       0ms
  localhost2.har                   4     0%   6269ms   8515ms  15740ms       0ms
  localhost3.har                   2   100%    506ms    790ms    790ms   12867ms
```

---

## 3. 詳細結果（localhost3.har）

### Folder-switch シーケンス

| lazy | IM time | IM body | RT time | RT body | interactive | content complete |
|------|---------|---------|---------|---------|-------------|-----------------|
| ✅  | 506ms   | 164KB   | 12867ms | 860KB   | **506ms**   | 13373ms         |
| ✅  | 790ms   | 163KB   | 4904ms  | 461KB   | **790ms**   | 5694ms          |

### コンポーネント頻度（localhost3.har）

```
58x  ledger.records-table-row   ← RecordsTableRow の lazy バッチ
 5x  ledger.records-table       ← Lazy ロード本体 (単独)
 4x  notifications.icon
 2x  ledger.index-manager       ← IM+placeholder バンドル
 2x  tenant-switcher, folder.tree
```

---

## 4. 効果の解釈

### ✅ 改善した点

#### interactive time: ~8.5s → ~790ms（中央値）
- フォルダクリック直後、ユーザーは **506〜790ms** でスケルトン UI を受け取る
- `localhost2.har` の同じ指標は **6.3〜15.7s**（プレースホルダーなし）

#### IM+placeholder バンドルのレスポンスが小さくなった
- 以前（Lazy なし）: IndexManager + RecordsTable bundle = **508〜895KB**
- 方針A 後（Lazy あり）: IndexManager + placeholder = **163〜164KB** ← ~3〜5 倍縮小

#### リクエスト多重化が抑制されている（localhost2 比）
- `localhost2.har`: 8 リクエスト（refreshChildren cascade ゼロ後の状態）
- `localhost3.har`: 14 リクエスト（RT の Lazy ロード + RecordsTableRow バッチが増えた分）

### ⚠️ 未改善・残課題

#### RecordsTable 単独ロードがまだ 5〜13s
```
standalone RecordsTable:
  4893ms  461KB   ← 少ないフォルダ
  4904ms  461KB   ← 同上
 12867ms  860KB   ← 台帳数が多いフォルダ
```
- 原因は `ColumnHtmlService::show()` の `view()->render()` を毎セル実行している Blade ループ
  （Issue #194 の直前コメント「方針B」に相当）
- 台帳数 × 列数 = 数百回の PHP サブビューコンパイルが 1 リクエスト内で直列実行

#### 体感的な「完了までの時間」は未改善
- `localhost2.har`: content complete = interactive = 6〜15s
- `localhost3.har`: interactive = 506〜790ms, **content complete = 5.7〜13.4s**
- ユーザーは即座に反応を得るが、実際のコンテンツの読み込みには依然 5〜13s 必要

---

## 5. 今後の要処置（優先度順）

### 優先度 1 — 方針B: ColumnHtmlService サブビュー描画の置き換え
- `ColumnHtmlService::show()` 内の `view('components.ledger.attachment-list', [...]) ->render()` を HTML 直接生成に置き換える
- 目標: RecordsTable 単独ロードを **<1s** に短縮
- 関連 Issue: #194 Sprint 5、方針B

### 優先度 2 — #200 状態ベースキャッシュ
- `ColumnHtml::show()` 結果を `Ledger ID + Column ID + updated_at` キーでキャッシュ
- 同一レコードの再描画コストをゼロにする
- 方針B と組み合わせ可能

### 優先度 3 — RecordsTableRow バッチの最適化
- `localhost.har` に見られた 89 件 → `localhost2.har` で 8 件 → `localhost3.har` で 14 件
- `RecordsTableRow` は 18〜22 本同時リクエスト（これ自体は期待動作）
- 各 Row が 108〜174KB 返しており、大量のセルを描画している

---

## 6. 測定コマンド

```bash
python3 docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py \
    localhost.har localhost2.har localhost3.har
```

---

## 7. 参照

- Issue: [#194](https://github.com/torinky/LedgerLeap/issues/194)
- コミット: `0c6f0f99` (方針A: lazy-load RecordsTable)
- ハーネス: `docs/harnesses/browser-har-analysis/scripts/har_lazy_analysis.py`
- 先行証跡: `docs/work/ui-ux/2026-03-20_livewire_update_duplication_completion_report.md`

