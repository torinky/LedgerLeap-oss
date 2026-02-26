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
        * 入力形式は、`text`, `textarea`, `select`, `check`, `datetime`, `upload`, `auto_numbering`, `number` があります。
        * `textarea` 型のカラムでは、**Markdown記法**が利用可能です。これにより、太字、箇条書き、コードブロックなどを使って、表現力豊かなテキストを記録できます。詳細は [MaryUI Markdownコンポーネント](https://mary-ui.com/components/markdown) を参照してください。
        * `number` 型では、テキスト入力欄とスライダーが連動したUIを提供し、台帳定義で最小値、最大値、刻み幅、単位を設定できます。登録された数値は、詳細画面やリスト画面で単位付きで表示されます。
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

### 作成・編集後の画面連携

台帳のレコードを新規作成または編集した後、ユーザー体験を向上させるための画面連携機能です。
具体的には、以下の2つの要件を同時に満たします。

1.  **遷移先でのフィードバック:** 保存後、遷移した詳細画面で「保存しました」という成功トーストを表示する。
2.  **一覧画面の自動更新:** 別ウィンドウで開かれている台帳一覧画面のリストを、ページ全体をリロードすることなく、裏側で静かに更新する。

#### 実装概要

この機能は、ワークフローの有効/無効によって、利用される技術や画面遷移の挙動が異なります。

##### 1. ワークフロー無効時の場合 (直接保存)

ワークフローが無効な台帳では、保存処理は単一のステップで完了し、ユーザーは即座に結果（詳細画面）を確認できることが期待されます。

*   **フロー:**
    1.  `CreateColumn` コンポーネントの `saveDirectly` メソッドが実行されます。
    2.  メソッドの最後で、MaryUIの `Toast` トレイトが提供する `$this->success(redirectTo: ...)` を呼び出します。
    3.  この時、`redirectTo` で指定するURLに、親ウィンドウ更新の目印として `?refresh=true` というクエリ文字列を付与します。
    4.  LivewireのSPAナビゲーション機能により、ページ全体のリロードなしに詳細画面 (`Show` コンポーネント) へ遷移します。
    5.  遷移先の `Show` コンポーネントも `Toast` トレイトを持っているため、トースト情報が引き継がれ、画面上に成功トーストが表示されます。
    6.  同時に、`Show` コンポーネントは `mount` 時にURLの `?refresh=true` を検知し、`localStorage` に更新指令を書き込みます。
    7.  一覧画面側では、`localStorage` の変更を監視している `storage` イベントリスナーが作動し、`Livewire.dispatch('ledgerStored')` を実行してリスト部分のみを更新します。

*   **関連ファイル:**
    *   `app/Livewire/Ledger/CreateColumn.php` (`saveDirectly` メソッド)
    *   `app/Livewire/Ledger/Show.php` (`mount` メソッド)
    *   `resources/views/layouts/appWithDrawer.blade.php` (`storage` イベントリスナー)

##### 2. ワークフロー有効時の場合 (下書き保存など)

ワークフローが有効な場合、ユーザーは保存後に続けて点検依頼などの操作を行う可能性があるため、画面遷移は行いません。

*   **フロー:**
    1.  `ModifyColumn` コンポーネントの `saveDraft` などのメソッドが実行されます。
    2.  メソッドの最後で、`$this->success()` を呼び出し、現在の編集画面に成功トーストを表示します。
    3.  同時に、`$this->js(...)` ヘルパーを使い、`window.opener.Livewire.dispatch('ledgerStored')` を実行するJavaScriptを直接呼び出します。
    4.  これにより、別ウィンドウの一覧画面のリスト部分のみが更新されます。

*   **関連ファイル:**
    *   `app/Livewire/Ledger/ModifyColumn.php` (`saveDraft`, `saveChangesAndReturnToDraft` メソッド)

#### 技術的キーポイント

*   **遷移先でのトースト表示:** MaryUIの `Toast` トレイトが持つ `redirectTo` 機能と、LivewireのSPAナビゲーションの連携によって実現されます。遷移元と遷移先の両方のコンポーネントが `Toast` トレイトを持つことが重要です。
*   **ウィンドウ間通信:** `window.opener` への直接アクセスはリダイレクトを挟むと不安定になるため、`localStorage` と `storage` イベントを利用した、より信頼性の高い方法を採用しています。これにより、ウィンドウ間で直接の参照を持つことなく、非同期に通信できます。

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
