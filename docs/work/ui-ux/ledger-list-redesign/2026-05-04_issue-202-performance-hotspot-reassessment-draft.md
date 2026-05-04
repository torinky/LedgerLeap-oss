# Issue #202: 台帳一覧の性能仮説の再評価

## GitHub 追跡

- Issue: #202（本件ドラフト）
- 関連: #194 / #192 / #199

## イシュー種別

調査

## 概要

台帳一覧の表示遅延について、添付ファイルカラムを主因候補から外し、`view()->render()` を含むセル描画経路の負荷を再評価したい。

## 背景 / 目的

- 添付ファイルカラムの有無で台帳の表示速度が大きく変わらない観測がある。
- 添付ファイルカラムは既に別 Livewire 経路で lazy-load 化されており、一覧本体の主因として扱うには弱い。
- その一方で、`ColumnHtmlService::show()` 内の `view()->render()` が重いという仮説はあるが、セル単位の内訳が分離計測されていない。
- 以上を踏まえ、Issue #202 では「何が遅いか」を断定する前に、計測対象を整理し直す。

## 現状

- 添付カラムの実体は `resources/views/components/ledger/table-row.blade.php` から `livewire:ledger.records-table-row defer` に分離されている。
- `app/Livewire/Ledger/RecordsTableRow.php` は `#[Lazy(isolate: false)]` で、添付列は一覧本体の初回描画とは別経路になっている。
- `app/Services/Ledger/ColumnHtmlService.php` は `prepareFilesData()` と `view('components.ledger.attachment-list', ...)->render()` を持つが、現状のログでは `render()` と Blade 側のコストが分離されていない。
- `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md` では、添付カラムを主因とみなす表現がまだ強い。

## 目標 / 完了状態

- 添付ファイルカラムを主因候補から外す根拠を、コード構造と計測結果の両面から説明できる。
- `view()->render()` の影響は、仮説ではなく計測結果に基づいて扱える。
- 1レコード分 / 1セル分 / 添付列分の所要時間を切り分けて、どこを最適化対象にするか判断できる。

## スコープ / 非スコープ

### 対象

- `RecordsTable` の再描画経路の切り分け
- `ColumnHtmlService::show()` と添付一覧 Blade の分離計測
- 添付列が主因でない場合の文面修正
- 関連する作業記録の見直し

### 対象外

- 添付ファイル機能の仕様変更
- `RecordsTableRow` の UI 置換
- 画面全体のデザイン変更

## 方針候補 / メモ

1. `ColumnHtmlService::show()` の内部を `prepareFilesData()` と `view()->render()` に分けて計測する
2. 1レコード分の `td` 単位で、添付列と非添付列の描画時間を比較する
3. 必要なら `RecordsTableRow` 側の lazy-load 描画時間も別途計測する
4. 計測結果に応じて、Issue #194 の文面を「主因」ではなく「未検証仮説」に修正する

## スプリント分解

- [x] Sprint 1: 既存コードとログから、添付列が主因候補として弱い理由を整理する
  - Evidence:
	- `resources/views/components/ledger/table-row.blade.php` で添付列は `livewire:ledger.records-table-row defer` に分離されている
	- `app/Livewire/Ledger/RecordsTableRow.php` は `#[Lazy(isolate: false)]` で、一覧本体の初回描画とは別経路になっている
	- `app/Services/Ledger/ColumnHtmlService.php` は `prepareFilesData()` と `view('components.ledger.attachment-list', ...)->render()` を持つが、現状は render 分離計測が未実施
	- `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md` の表現を、添付列を主因断定しない方向へ更新済み
- [x] Sprint 2: `prepareFilesData()` / `view()->render()` / セル描画を分離計測する
  - Evidence:
	- `app/Services/Ledger/ColumnHtmlService.php` に `column_html_show_ms` / `column_html_prepare_files_ms` / `column_html_blade_render_ms` を追加
	- `table-row` / `ledger-detail-table` / `ledger-content-processor` で source を付与し、ログ相関できるように整理
	- `tests/Feature/Components/TableRowAttachmentLoggingTest.php` を更新し、`prepareFilesData()` と `getFileHtml()` の分離ログを検証
	- `./vendor/bin/sail test tests/Unit/Services/Ledger/ColumnHtmlServiceTest.php tests/Feature/Components/TableRowAttachmentLoggingTest.php tests/Feature/Components/AttachmentListComponentTest.php` が PASS
- [x] Sprint 3: 追加計測の結果をもとに Issue #194 と関連文書を更新する
  - Evidence:
	- `docs/work/ui-ux/ledger-list-redesign/2026-05-04_column_html_benchmark.php` で files 列を 15 回ずつ計測
	- 4 添付の代表ケースでは `show()` median **8.761ms** / `prepareFilesData()` median **2.293ms** / Blade render median **4.751ms**
	- `prepareFilesData()` より Blade render の方が重いが、単体セルのコストは一桁 ms に収まり、一覧全体の 5〜13s を直接説明する主因ではない
	- `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md` の表現を実測結果に合わせて更新済み

## エビデンス / 参照先

- `app/Livewire/Ledger/RecordsTable.php`
- `app/Livewire/Ledger/RecordsTableRow.php`
- `app/Services/Ledger/ColumnHtmlService.php`
- `resources/views/components/ledger/table-row.blade.php`
- `resources/views/components/ledger/attachment-list.blade.php`
- `resources/views/livewire/ledger/records-table-row.blade.php`
- `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-194-lazy-effect-measurement.md`
- `docs/work/ui-ux/ledger-list-redesign/2026-05-03_issue-192-folder-switch-delay-retrospective.md`
- `docs/work/ui-ux/ledger-list-redesign/2026-05-04_issue-199-sprint1-separation-design.md`

## 完了条件

- [x] 添付ファイルカラムを主因として断定しない文面に直っている
- [x] `view()->render()` の負荷を、計測結果に基づいて扱える
- [x] 追加の所要時間計測が必要かどうかを判断できる
- [x] 関連する作業記録が新しい整理に合わせて更新されている

## 関連リンク

- Issue #194
- Issue #192
- Issue #199
- `docs/operations/ledger-records-performance-monitoring.md`

## 確認事項

- バグ報告ではないことを確認した
- 背景 / 現状 / 目標 / スコープを分けて書いた
- スプリント分解と完了条件を記入した
- 参照先やエビデンスを可能な範囲で添付した


