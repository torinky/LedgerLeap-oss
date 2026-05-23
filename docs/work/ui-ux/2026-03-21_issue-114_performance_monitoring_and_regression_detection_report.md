# Issue #114: 常時モニタ指標と回帰検知の整理 実装レポート

**作成日:** 2026-03-21  
**対象 Issue:** [#114 常時モニタ指標と回帰検知の整理](https://github.com/torinky/LedgerLeap/issues/114)  
**親 Issue:** [#111 台帳リストの初期表示が遅いので調査する](https://github.com/torinky/LedgerLeap/issues/111)

---

## 1. このドキュメントの整理思想

このレポートは、**「何を監視対象にしたか」ではなく「なぜその監視設計にしたか」** を残すための作業記録です。  
日常運用で見るべき基準は `docs/operations/ledger-records-performance-monitoring.md` に分離し、ここでは次の判断根拠を追えるようにします。

- 調査ログと常時モニタを分けた理由
- 常時モニタ対象を絞った理由
- 閾値を設定した理由
- どのテストで回帰を防いでいるか
- Issue #111 以降の運用整理にどうつながるか

つまり、この文書は「意思決定の履歴書」、運用仕様は「現場で使う手順書」という役割分担です。

---

## 2. 背景

Issue #111 / #113 の調査を通じて、台帳一覧の遅延は単なる SQL の重さだけでなく、Livewire の再レンダー連鎖や、計測イベントの扱い方にも影響されることが分かりました。  
その結果、次の課題が見えてきました。

- 調査時だけ必要な詳細メトリクスと、常時追うべき軽量メトリクスが混在していた
- 回帰検知の基準が明文化されておらず、再発時に比較しづらかった
- 計測が増えるほど、通常運用で追うべき情報が埋もれやすかった

Issue #114 は、この境界を整理し、**維持・運用フェーズで比較しやすい観測設計** に切り替えるための作業です。

---

## 3. 変更前の課題

### 3.1 調査ログと常時監視が同じ粒度だった

`ledger_records_render` などの広いレンダー指標と、`ledger_records_query_prep_ms` / `normalize_ms` のような内部分解ログが同列に扱われていました。  
そのため、普段見るべき指標と、原因調査のときだけ掘る指標が判別しづらい状態でした。

### 3.2 回帰検知の基準がコードに埋もれていた

性能計測は出力されていても、**どの値を超えたら異常扱いにするのか** が明文化されていませんでした。  
結果として、同じ数値でも「様子見」なのか「再調査」なのかを判断しにくくなっていました。

### 3.3 監視の出口が1つではなかった

JSON 統計ファイルと Laravel ログが併用されていましたが、運用上の入口としては整理不足でした。  
そこで、ログチャネルを分離し、警告系の出力先を明確にしました。

---

## 4. 採用した整理方針

### 4.1 常時モニタと調査用メトリクスを分ける

`config/ledgerleap.php` の `performance.monitoring` に次の3層を定義しました。

- `always_on_metrics`
  - 日常運用で継続監視する軽量指標
  - 例: `ledger_records_render`, `ledger_records_query_prep_ms`, `ledger_records_query_paginate_ms`, `normalize_ms`
- `investigation_metrics`
  - 調査時に原因切り分けのために参照する内部指標
  - 例: `display_ledger_defines_query_ms`, `attachments_fetch_ms`, `grouping_ms`, `view_prepare_ms`
- `thresholds_ms`
  - 回帰検知の警告閾値

この分離により、普段の確認対象は少数に保ちつつ、異常時だけ深掘りできる形にしました。

### 4.2 警告は専用チャネルへ分ける

`config/logging.php` に `performance` チャネルを追加し、閾値超過の warning を通常ログと分けました。  
これにより、運用担当は `performance.log` を見れば回帰兆候を素早く拾えます。

### 4.3 計測結果に観測レベルを付ける

`LogPerformance` に `monitoring_tier` を付加し、各ログがどの運用レベルのものかを後から追えるようにしました。

- `always_on`
- `investigation`
- `ad_hoc`

### 4.4 空の JSON 統計を壊さない

`performance_stats.json` が空の場合でも記録処理が壊れないように安全化しました。  
これは、手動初期化や運用上のリセット後でも監視を継続できるようにするためです。

---

## 5. 実装結果

### 5.1 変更ファイル

- `app/Livewire/Traits/LogPerformance.php`
- `config/logging.php`
- `config/ledgerleap.php`
- `tests/Unit/Livewire/Traits/LogPerformanceTest.php`

### 5.2 実装した内容

#### `LogPerformance`

- ログメタデータに `monitoring_tier` を追加
- `thresholds_ms` を参照して警告判定を追加
- 閾値超過時は `performance` チャネルへ warning を出力
- `performance_stats.json` が空でも安全に追記できるように修正

#### `config/ledgerleap.php`

- 常時モニタ対象を定義
- 調査用メトリクスを定義
- 閾値を定義
- warning の出力先チャネルを設定

#### `config/logging.php`

- `performance` チャネルを追加

### 5.4 補足: frontend 計測の扱い

FileInspector の `drawer_open` / `tab_switch` / `image_preview_load` は、現在は browser console / browser.log で確認する browser-only 測定に寄せています。  
そのため、これらのメトリクスを `LogPerformance` 経由で `performance_stats.json` に重複記録する経路は削除しました。

結果として、役割は次のように分離されます。

- `browser.log` / DevTools Console: フロントエンドの体感測定
- `performance_stats.json`: backend 側の詳細分析
- `performance-YYYY-MM-DD.log`: threshold 超過の warning

### 5.5 直近ログでの判定

`storage/logs/performance_stats.json` の直近 270 件を確認したところ、`ledger_records_render` が最も多く、次に `ledger_toggle_define` / `ledger_records_mount` / `ledger_index_mount` が続いていました。  
最近値では、`ledger_records_render` が概ね **95〜213ms**、`ledger_records_query_paginate_ms` が **41〜112ms**、`normalize_ms` が **7〜17ms** で推移しており、設定した閾値を大きく下回っています。

この観測から、現状の常時モニタ 6 項目は**過剰ではなく、回帰検知の最小構成として妥当**と判断できます。  
一方で、`ledger_records_query_prep_ms` はかなり小さく安定しているため、将来の数サイクルで変動が乏しければ、常時モニタから調査用へ降格する候補になります。

`performance` チャネルについては、今回の観測範囲では閾値超過 warning が出ておらず、`performance-YYYY-MM-DD.log` を追う場面はまだ発生していません。  
これは「警告が未実装」ではなく、**現時点では閾値を超える回帰が観測されていない** ことを意味します。

### 5.3 テスト結果

追加したテストでは次を確認しています。

1. 常時モニタ指標が `monitoring_tier=always_on` で記録されること
2. 閾値超過時に `performance` チャネルへ warning が飛ぶこと
3. 閾値未満では warning が発生しないこと
4. 空の `performance_stats.json` でも記録が失敗しないこと

実行結果:

```bash
./vendor/bin/sail test tests/Unit/Livewire/Traits/LogPerformanceTest.php
```

- `3 passed`

---

## 6. 再発防止メモ

- 計測メトリクスは、**調査用と常時監視用を混ぜない**
- 閾値はコード上の数値だけでなく、運用文書でも同じ値を参照できるようにする
- 新しいメトリクスを追加したら、
  - 常時モニタか
  - 調査用か
  - 閾値対象か
  - 通常ログか警告ログか
  を必ず決める
- 監視の設計が増えたときは、作業記録に判断理由を残し、運用手順は別文書へ逃がす

### 6.1 追加・廃止の判断基準

今後、新しい測定パラメータを追加する場合は、以下を満たすときだけ採用します。

- 既存指標では説明できない独立した回帰兆候である
- 複数回の実測で再現性がある
- 閾値を置ける
- 日常運用で毎回見る価値がある

逆に、廃止または降格する条件は次のとおりです。

- 他指標と完全に冗長
- 数サイクル連続で変動がほぼない
- ノイズが多い
- 常時収集するコストが高い

### 6.2 HAR / Network を必須にする場面

ログだけでは検知できない回帰もあります。たとえば Livewire の**重複リクエスト**や、初期描画前の**往復回数**は `performance_stats.json` では完全には見えません。  
そのため、再発時に「リクエストが増えたのか」「サーバー内処理が重いのか」を切り分ける必要がある場合は、HAR / Network を併用する運用を継続します。

---

## 7. この変更で得られたこと

- 再発時に比較する基準が明確になった
- 調査ログのノイズを減らせた
- 運用担当が見るべきログが分かれた
- 監視の境界がコードと文書の両面で説明できるようになった

---

## 8. 参照先

- 運用仕様: `docs/operations/ledger-records-performance-monitoring.md`
- 実装: `app/Livewire/Traits/LogPerformance.php`
- 設定: `config/ledgerleap.php`
- ログチャネル: `config/logging.php`
- テスト: `tests/Unit/Livewire/Traits/LogPerformanceTest.php`



