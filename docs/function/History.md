# 変更履歴機能

## 概要

変更履歴機能は、LedgerLeap
において、データの変更内容を記録し、追跡するための機能です。この機能により、誰が、いつ、どのようにデータを変更したのかを把握し、データの透明性と信頼性を確保することができます。Ledger（台帳）のレコードや、LedgerDefine(
台帳定義)の変更は、`App\Models\LedgerDiff`で管理します。ユーザーの操作は、`Spatie\Activitylog`パッケージを利用して、
`App\Models\CustomActivity`に記録されます。

## 機能詳細

### 台帳の変更記録

* 台帳のデータの追加、更新、削除などの操作が行われた場合、`App\Models\LedgerDiff`にその変更履歴が記録されます。
* `App\Models\LedgerDiff`には、変更が行われた時刻、変更を行ったユーザー、変更前後の値などが記録されます。
* `App\Models\LedgerDefine`の変更内容についても、`App\Models\LedgerDiff`に記録されます。

### ユーザーのアクティビティ

* ユーザーが台帳データやLedgerDefineに対して行った操作（作成、更新、削除など）は、`Spatie\Activitylog`パッケージによって
  `App\Models\CustomActivity`に記録されます。
* 変更したユーザーや、変更した日時を確認することができます。

### 変更履歴の確認

* 台帳やLedgerDefineの変更履歴は、詳細画面で確認することができます。
* 変更前の値と変更後の値を比較することができます。
* 台帳やLedgerDefineを変更したユーザーを確認することができます。
* 台帳を変更した時の通知については、`App\Services\NotificationService`で管理します。

### 変更のトリガー

変更履歴は、以下のタイミングで記録されます。

* 台帳データの作成
* 台帳データの更新
* 台帳データの削除
* 台帳定義の作成
* 台帳定義の更新
* 台帳定義の削除
* モデルが変更された時

## 関連ファイル

* `App\Models\LedgerDiff`: 台帳データの変更履歴を管理するモデル。
* `App\Models\CustomActivity`: ユーザーのアクティビティを記録するモデル。
* `Spatie\Activitylog`: ユーザーの操作を記録するパッケージ。
* `App\Services\NotificationService`: 変更の通知を管理するサービス。
* `/lang/ja/ledger.php`: 日本語の翻訳ファイル
