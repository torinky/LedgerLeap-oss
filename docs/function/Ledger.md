# 台帳管理機能

## 概要

台帳管理機能は、LedgerLeap の主要機能の一つであり、様々な情報を構造化して記録・管理するための機能です。ユーザーは台帳を使って、データを登録、編集、削除できます。また、台帳はフォルダーに整理できます。台帳の枠組みは、
`LedgerDefine`で定義します。台帳のレコードを変更した時は、`LedgerDiff`に記録します。ユーザーの操作は`Spatie\Activitylog`
で管理します。

## 機能詳細

### 台帳の作成

台帳を新規に作成する機能を説明します。

* まず、`App\Filament\Resources\LedgerDefineResource`を使って、台帳の枠組みを定義します。
    * 台帳のタイトル、説明を設定します。
    * 列を設定します。列のタイトル、入力形式、必須入力、重複禁止などの属性を設定します。
        * 入力形式は、`text`, `textarea`, `select`, `check`, `datetime`, `upload`, `auto_numbering`があります。
        * 列は、並び替えが可能です。
        * 列は、追加や削除ができます。
        * 必須入力を設定できます。
        * 重複を禁止を設定できます。
        * 設定した台帳定義は、`App\Models\LedgerDefine`に保存されます。
* 次に、`App\Filament\Resources\LedgerResource`を使って、台帳を作成します。
    * 作成する台帳の枠組みとして、`App\Models\LedgerDefine`を選択します。
    * `App\Models\Ledger`を登録します。
        * 作成した`App\Models\LedgerDefine`と紐づきます。
        * 台帳のタイトル、説明、台帳名、公開設定などを設定します。
    * 作成時に、通知を送信します。

### 台帳の編集

作成した台帳を編集する機能を説明します。

* `App\Filament\Resources\LedgerResource`を使って、編集します。
* `App\Services\LedgerService`を使って、編集します。
    * 台帳のレコードの変更や、列の情報を変更します。
    * 台帳のレコードを変更した時は、`App\Models\LedgerDiff` に記録されます。
* 編集時に、通知を送信します。

### 台帳の削除

不要になった台帳を削除する機能を説明します。

* `App\Filament\Resources\LedgerResource`を使って、削除します。
* 削除した時、関連する台帳のレコードも削除されます。
* 削除時に、通知を送信します。

### 台帳の構造

台帳は、複数の列を持つことができます。それぞれの列は、タイトル、入力形式、必須入力、重複禁止などの属性を設定できます。また、台帳で入力されたデータは、変更履歴が記録されます。

### 台帳とフォルダーの関係

台帳は、フォルダーに所属します。フォルダーは、台帳を整理するための機能です。

* 台帳の作成時に、フォルダに紐づけます。

### 台帳の変更記録

台帳の変更は、`LedgerDiff`によって、管理されます。

* 台帳のどのレコードが、どのように変更されたか管理します。
* `App\Models\LedgerDiff`で管理されます。

### ユーザーのアクティビティ

ユーザーの操作は、`Spatie\Activitylog`で管理されます。

* いつ、だれが、どのように変更したか確認することができます。
* `App\Services\NotificationService`で、通知します。

## 関連ファイル

* `App\Models\Ledger`: 台帳のモデル。
* `App\Models\LedgerDefine`: 台帳の枠組みを定義するモデル。
* `App\Models\LedgerDiff`: 台帳の変更記録を管理するモデル。
* `App\Http\Resources\LedgerResource`: APIレスポンスで利用されるリソース。
* `app/Filament/Resources/LedgerResource.php`: Ledgerモデルを管理します。
* `app/Filament/Resources/LedgerDefineResource.php`: LedgerDefineモデルを管理します。
* `App\Services\LedgerService`: Ledgerに関連する処理を行います。
* `App\Models\Folder`: 台帳を整理するフォルダです。
* `Spatie\Activitylog`: 変更履歴の記録を行うパッケージ。
* `App\Models\CustomActivity`: ユーザーの操作を記録するモデルです。
* `/lang/ja/ledger.php`: 日本語の翻訳ファイル
