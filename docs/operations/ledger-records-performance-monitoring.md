# 台帳レコード性能監視ガイド

**最終更新:** 2026-03-21  
**対象:** `ledger.records-table` / 台帳一覧の常時モニタと回帰検知  
**関連作業記録:** `docs/work/ui-ux/2026-03-21_issue-114_performance_monitoring_and_regression_detection_report.md`

---

## 1. このドキュメントの整理思想

この文書は、**日常運用で「何を見て、何を超えたら対応するか」を迷わないための手順書** です。  
作業記録は「なぜその設計にしたか」を残しますが、この文書は毎日の確認や障害時の初動で即使えることを重視します。

そのため、以下の順番で整理しています。

1. まず見る指標
2. 次に深掘りする指標
3. 閾値
4. 異常時の対応
5. ログとファイルの確認方法
6. テストで何を担保しているか

---

## 2. 監視対象の考え方

台帳一覧の性能監視は、次の3層で考えます。

### 2.1 常時モニタ

毎日または定期的に確認する軽量指標です。  
回帰の有無を素早く判断するために使います。

### 2.2 調査用メトリクス

異常時や再現確認時だけ掘る詳細指標です。  
原因切り分けのために使い、日常確認では細かく追いません。

### 2.3 閾値アラート

常時モニタの数値が一定値を超えた場合に warning を出します。  
「ログを見て異常に気づく」だけでなく、「超過したら自動で警告が残る」ことを目的にしています。

---

## 3. 常時モニタ指標

`config/ledgerleap.php` の `performance.monitoring.always_on_metrics` を基準にします。

| メトリクス | 意味 | 見る理由 | 目安 |
|---|---|---|---|
| `ledger_records_render` | 台帳一覧全体の描画時間 | 一覧の体感遅延を最初に把握する | 1000ms 以下 |
| `ledger_records_query_prep_ms` | 一覧取得前の準備時間 | 検索条件や対象台帳の準備遅延を確認する | 250ms 以下 |
| `ledger_records_query_paginate_ms` | ページング取得時間 | 取得件数やページングの負荷を確認する | 250ms 以下 |
| `ledger_init_overlay_hidden` | 初期オーバーレイが消えるまでの時間 | 初期表示の体感速度を確認する | 300ms 以下 |
| `ledger_init_overlay_painted` | オーバーレイ後の描画完了までの時間 | 実際に画面が見えるまでの遅延を確認する | 350ms 以下 |

> 補足: このガイドでは、常時モニタは「回帰を疑う最低限の指標」に絞っています。  
> 詳細な内訳が必要な場合だけ、次の調査用メトリクスを見ます。

---

## 4. 調査用メトリクス

`config/ledgerleap.php` の `performance.monitoring.investigation_metrics` を基準にします。

### 4.1 代表的な指標

| メトリクス | 使いどころ |
|---|---|
| `display_ledger_defines_ms` | 台帳定義の描画に時間がかかるとき |
| `display_ledger_defines_query_ms` | 台帳定義の取得が遅いとき |
| `display_ledger_defines_load_ms` | eager load / hydration の切り分け |
| `breadcrumbs_prepared_ms` | パンくず生成が重いとき |
| `ledger_records_query_ms` | 実データ取得の重さを見るとき |
| `attachments_fetch_ms` | 添付件数取得や集計の重さを見るとき |
| `search_hit_mark_ms` | 検索ヒットのマーキング処理を疑うとき |
| `current_user_permission_ms` | 権限判定が重いとき |
| `filtered_column_defines_ms` | 表示カラムの絞り込みが重いとき |
| `score_stats_ms` | スコア統計が重いとき |
| `grouping_ms` | 台帳定義ごとのグルーピングが重いとき |
| `view_prepare_ms` | Blade に渡す直前の総合負荷を確認するとき |

### 4.2 調査時の使い方

- 常時モニタがしきい値を超えたら、まず `ledger_records_query_prep_ms` と `ledger_records_query_paginate_ms` を確認する
- 一覧の描画が遅いが SQL か UI か切り分けられない場合は `ledger_records_query_ms` と `view_prepare_ms` を比較する
- 件数が少なくても遅い場合は `grouping_ms` / `score_stats_ms` のどこが支配的かを見る

---

## 5. 閾値一覧

`config/ledgerleap.php` の `performance.monitoring.thresholds_ms` を基準にします。

| メトリクス | 閾値 | アラート扱い |
|---|---:|---|
| `ledger_records_render` | 1000ms | warning |
| `ledger_records_query_prep_ms` | 250ms | warning |
| `ledger_records_query_paginate_ms` | 250ms | warning |
| `ledger_init_overlay_hidden` | 300ms | warning |
| `ledger_init_overlay_painted` | 350ms | warning |

> ルール: 閾値を超えたら `storage/logs/performance-YYYY-MM-DD.log` を優先的に確認します。  
> 1回の超過ですぐ障害とは断定せず、同じ指標が連続して超えたかを見るのが基本です。

---

## 6. ログとファイルの確認方法

### 6.1 Laravel 標準ログ

warning は `performance` チャネルに出ます。`daily` ローテートのため、実ファイルは `storage/logs/performance-YYYY-MM-DD.log` 形式です。

```bash
./vendor/bin/sail exec laravel-1 tail -f storage/logs/performance-$(date +%Y-%m-%d).log
```

### 6.2 JSON 統計ファイル

詳細な時系列確認は `storage/logs/performance_stats.json` を使います。

```bash
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.'
```

### 6.3 代表的な絞り込み例

```bash
# 常時モニタだけ見る
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json \
  | jq '.[] | select(.metric == "ledger_records_render" or .metric == "ledger_records_query_prep_ms" or .metric == "ledger_records_query_paginate_ms")'

# 閾値超過だけ見る
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json \
  | jq '.[] | select(.threshold_exceeded == true)'
```

---

## 7. 運用フロー

### 7.1 毎日の確認

1. `performance.log` を確認する
2. `ledger_records_render` の超過有無を見る
3. 超過があれば `performance_stats.json` で同時刻の周辺指標を見る
4. 必要なら `ledger_records_query_prep_ms` / `ledger_records_query_paginate_ms` へ掘る

### 7.2 回帰を疑うとき

1. 変更前後の `ledger_records_render` を比較する
2. `ledger_records_query_prep_ms` と `ledger_records_query_paginate_ms` のどちらが増えたかを見る
3. `ledger_init_overlay_hidden` / `ledger_init_overlay_painted` が増えていれば、初期表示の描画経路を疑う

### 7.3 しきい値超過時の初動

- まず `performance-YYYY-MM-DD.log` の warning を確認する
- 同じ指標が複数回超過しているか確認する
- 連続して超過しているなら Issue / 変更内容 / 直近マージを確認する
- 必要なら `docs/work/ui-ux/` 側に調査記録を追加し、詳細分析へ切り替える

---

## 8. 測定パラメータの追加・廃止基準

### 8.1 追加してよい条件

次の条件を**すべて満たす**場合のみ、新しい常時モニタ候補として追加します。

1. **独立した回帰兆候であること**
   - 既存メトリクスの組み合わせでは説明しきれない
   - その指標だけで異常の切り分けが進む
2. **再現性があること**
   - 1回限りの偶然ではなく、複数回の実測で同じ傾向が出る
3. **閾値を置けること**
   - 正常/異常の境界を数値で説明できる
4. **運用者が毎日見る価値があること**
   - 調査時だけ必要なノイズではない

### 8.2 廃止または降格してよい条件

次のいずれかに当てはまる場合は、常時モニタから外すか、調査用へ降格します。

1. **他指標と完全に冗長**
   - 既存の常時モニタで同じ異常を検知できる
2. **変動がほぼない**
   - 複数リリースにわたって差分が小さく、回帰検知に寄与しない
3. **ノイズが多い**
   - 実運用では日常的にブレるだけで、異常判定の妨げになる
4. **常時監視のコストが高い**
   - 収集・保存・可視化の負荷が見合わない

### 8.3 今回の判定

直近の `performance_stats.json` では、`ledger_records_render` が 111 件記録され、`ledger_records_query_prep_ms` / `ledger_records_query_paginate_ms` / `normalize_ms` / `ledger_init_overlay_hidden` / `ledger_init_overlay_painted` も継続して出力されています。  
実測の最近値でも、`ledger_records_render` は概ね **95〜213ms**、`ledger_records_query_paginate_ms` は **41〜112ms**、`normalize_ms` は **7〜17ms** 程度で、いずれも設定閾値を大きく下回っていました。

このため、現状の常時モニタ 6 項目は**過剰ではなく、回帰検知に必要な最小構成として妥当**と判断します。  
一方で、`ledger_records_query_prep_ms` は実測上かなり小さいため、今後も数サイクル連続で変動が乏しい場合は、**常時モニタから調査用への降格候補**として扱って構いません。

### 8.4 追加・廃止の運用ルール

- 追加は「2件以上の再発ログ」または「HAR / Network でも再現する新しい回帰軸」が出たときだけ行う
- 廃止は「3サイクル以上の監視で冗長・無変化」が確認できたときだけ行う
- 変更する場合は、作業記録 (`docs/work/...`) と運用仕様 (`docs/operations/...`) の両方を更新する

---

## 9. テストで担保していること

- `tests/Unit/Livewire/Traits/LogPerformanceTest.php`
  - 常時モニタ指標が正しく記録されること
  - 閾値超過時に warning が出ること
  - 空の JSON ファイルでも追記できること
- `tests/Feature/Livewire/Ledger/RecordsTableActionsTest.php`
  - `ledger_records_render` の出力項目が壊れていないこと
  - 主要な細分化指標が継続して出ること

> 運用時の判断は、このテスト群とログ出力が両方そろっていることを前提にします。

---

## 10. 異常時の連携先

- 調査記録: `docs/work/ui-ux/2026-03-21_issue-114_performance_monitoring_and_regression_detection_report.md`
- 実装: `app/Livewire/Traits/LogPerformance.php`
- 設定: `config/ledgerleap.php`
- ログ設定: `config/logging.php`

---

## 11. まとめ

この運用仕様では、台帳一覧の性能を「全部見る」のではなく、**回帰を検知する最低限の指標だけを常時見る**ようにしています。  
異常時は調査用メトリクスへ掘り下げ、必要なら作業記録へ切り替えることで、日常運用と調査を分離します。


