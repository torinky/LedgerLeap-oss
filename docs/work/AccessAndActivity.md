# アクセスとアクティビティ実装作業計画と実績

---

### **✅ ステップ 1: `App\Livewire\Common\ActivityHistoryDisplay` の基本実装 (完了)**

* **目的**: どのリソースタイプ（Ledger, LedgerDefine, Folder）にも対応できる共通のアクティビティ履歴表示コンポーネントの作成。
  **また、リソースタイプとIDが指定されない場合はシステム全体の活動履歴を全件表示するモードをサポートする。**
* **作業内容**:
    1. `app/Livewire/Common/ActivityHistoryDisplay.php` を作成。
    2. コンポーネント内で `$resourceId`, `$resourceType` プロパティ（nullable）および `includeRelatedResources`
       プロパティを受け取るように定義。
    3. `mount()` メソッドで、これらのプロパティを初期化。
    4. `getActivitiesQuery()` メソッドで、`$resourceType` と `$resourceId` が指定された場合はそれらに基づいて
       `CustomActivity::query()` を構築し、関連リソース（`Ledger` から `LedgerDefine`, `Folder` など）のアクティビティも取得するロジックを実装。
       **指定されない場合は全件を取得するロジックを実装。**
        * `Spatie\Activitylog\Models\Activity` モデル（`CustomActivity` が継承）の `subject_id`, `subject_type`
          を利用してフィルタリング。
        * `with('causer', 'subject')` で関連するユーザーと対象モデルをイーガーロード。
    5. `resources/views/livewire/common/activity-history-display.blade.blade.php` を作成。
    6. 取得したアクティビティログを、日時、操作者、操作内容、対象リソースタイプ/名称、コメントの形式で表示。*
       *表示には `x-mary-table` コンポーネントを使用し、テーブルのヘッダーとセルを `x-slot:cell_...` で定義。**
    7. 日時によるソート（昇順/降順）とページネーションを実装。
    8. `getOperationDescription()` メソッドを実装し、`event` や `subject_type` に基づいてユーザーフレンドリーな操作内容の文字列を生成。
       `workflow` や `attached`/`detached` などの特定イベントに対応。
    9. `formatChanges()` メソッドを実装し、`properties` 内の `attributes` と `old` から変更差分を抽出し、HTML形式で表示。
       `latest_diff_id` や自動更新されるカラムは表示から除外。**DaisyUIのテキストカラークラス (`text-success`,
       `text-error` など) を適用し、変更を視覚的に分かりやすく表現。**JSONカラムの変更は「コンテンツ変更あり」と表示。
    10. `getSubjectDisplay()`、`getCauserDisplayName()`、`formatComment()` メソッドを実装し、表示内容を整形。
    11. `getSubjectDetailLink()` メソッドで、`Ledger` は `ledger.show` へ、`Folder` は `ledgersByFolderId` へリンクするように設定。
        `LedgerDefine` は `ledgerByDefineId` へリンクするように設定。その他の管理系モデルへのリンクは `null` (
        一般ユーザー向け画面では表示しない)。
    12. `render()` メソッドの冒頭で `auth()->user()->can('viewAny', \App\Models\CustomActivity::class)`
        を使ってログ閲覧権限をチェックし、権限がない場合は
        `livewire/common/activity-history-display-no-permission.blade.php` ビューを返すように実装。
    13. `resources/views/livewire/common/activity-history-display-no-permission.blade.php` を作成。
    14. 必要な翻訳キーを `lang/ja/ledger.php` に統合・追加。`lang/ja/activitylog.php` は削除または空にする。
* **動作確認**:
    * 各種リソース（Ledger, LedgerDefine, Folder）のアクティビティが、それぞれ `$resourceId` と `$resourceType`
      の指定に応じて正しく表示されることを確認。
    * `includeRelatedResources` が `true` の場合、関連する親階層のアクティビティも表示されることを確認。
    * `$resourceId` と `$resourceType` を指定しない場合（例: `@livewire('common.activity-history-display')`
      ）、全システムのアクティビティログが正しく表示されることを確認。
    * 権限がないユーザーでアクセスした場合、権限がない旨のメッセージが表示されることを確認。
    * 変更差分表示がDaisyUIカラーとともに行われ、見やすいことを確認。
    * ページネーションが正しく動作することを確認。
    * リンクが正しく機能することを確認。
* **成果物**: 汎用的なアクティビティ履歴表示コンポーネントが完成。特定のモデルに紐づくアクティビティと、システム全体の活動履歴の両方を一貫したUIで提供可能になった。

---

### **ステップ 1.5: `UserActivityLog` の削除と `ActivityHistoryDisplay` への統合、`NotificationList` からのActivity
Log表示ロジックの共通化**

* **目的**:
    * 重複するアクティビティログ表示ロジックを `ActivityHistoryDisplay` に集約し、`UserActivityLog` コンポーネントを削除する。
    * `NotificationList` コンポーネント内でActivity Logの変更差分を表示するロジックを、`ActivityHistoryDisplay` の
      `formatChanges` メソッドと共通化する。
    * これにより、コードの保守性を高め、UI/UXの一貫性を確保する。

* **作業内容**:

    1. **`UserActivityLog` コンポーネントの削除と置き換え**:
        * `app/Livewire/UserActivityLog.php` ファイルを削除。
        * `resources/views/livewire/user-activity-log.blade.php` ファイルを削除。
        * `app/Http/Controllers/UserNotificationController.php` (
          統合された通知・アクティビティログ・タスク画面のコントローラー) を修正し、`ActivityHistoryDisplay` を呼び出すように変更。
            * 具体的には、`resources/views/notifications/index.blade.php` 内で `@livewire('user-activity-log')` を
              `@livewire('common.activity-history-display')` に置き換える。
            * （`ActivityHistoryDisplay` は `$resourceId`, `$resourceType` が `null` の場合に全件表示モードになるため、引数なしで呼び出す）

    2. **`NotificationList` からのActivity Log表示ロジックの共通化**:
        * `app/Livewire/Notifications/NotificationList.php` を修正。
        * 現在 `formatNotificationData` メソッド内で `changes` を処理し、`x-diff-display` コンポーネントを呼び出している部分がある。
        * この `changes` の整形ロジックを、`ActivityHistoryDisplay` が持つ `formatChanges` メソッドと同等にする必要がある。
        * **対応方針検討**: `ActivityHistoryDisplay::formatChanges` はインスタンスメソッドであるため、`NotificationList`
          から直接呼び出すのは適切ではありません。以下のいずれかの方針を検討します。
            * **A) ActivityLogFormatter サービス/トレイトの導入（推奨）**: `formatChanges`
              ロジックを独立したサービスまたはトレイト（例: `App\Helpers\ActivityLogFormatter` または
              `App\Traits\FormatActivityLogChanges`）として切り出す。これを `ActivityHistoryDisplay` と `NotificationList`
              の両方から利用するようにする。
            * **B) `NotificationList` の ActivityLog 表示を簡略化**: `NotificationList`
              では変更差分の詳細表示はせず、単に「変更あり」のようなメッセージに留め、詳細が必要な場合は `Ledger/Show.php`
              の「活動履歴」タブへのリンクを促す。

---

#### **ステップ 2: `App\Livewire\Common\PermissionDisplay` の基本実装**

* **目的**: どのリソースタイプ（Ledger, LedgerDefine, Folder）にも対応できる共通の権限情報表示コンポーネントの作成。
* **作業内容**:
    1. `app/Livewire/Common/PermissionDisplay.php` を作成。
    2. コンポーネント内で `$resourceId`, `$resourceType` プロパティを受け取るように定義。
    3. `mount()` メソッドで、`$resourceType` と `$resourceId` に基づいて、そのリソースに最終的に適用される権限情報を取得するロジックを実装。
        * `Ledger` の場合は、親の `LedgerDefine` を経由して `Folder` の権限を取得。
        * `Folder` の場合は、`RoleFolderPermission` を直接参照。
        * `LedgerDefine` の場合は、`HasModelRoles` で直接紐づいたロールと、親 `Folder` の権限を取得。
        * `UserService` や `Folder` モデルの既存の権限関連メソッド（`getAllPermissionsWithInheritance` など）を最大限活用。
    4. ログインユーザーが持つ権限の概要を判定し、表示するロジックを実装。
    5. ビュー `resources/views/livewire/common/permission-display.blade.php` を作成。
    6. 取得した権限情報を、階層的な権限元、ロールごとの権限タイプ（アイコン/バッジ付き）の形式で表示。
    7. アクセス可能なユーザー/ロール一覧を表示し、ページネーションを実装。

#### **ステップ 3: `Ledger/Show.php` (レコード詳細画面) への共通コンポーネント統合**

* **目的**: 台帳レコード詳細画面に、作成した共通コンポーネントをタブとして組み込む。
* **作業内容**:
    1. `app/Livewire/Ledger/Show.php` を修正。
    2. `show.blade.php` に新たなタブを追加。例えば「総合活動履歴」「アクセスと権限」。
    3. 各タブ内に、対応する共通コンポーネント (
       `@livewire('common.activity-history-display', ['resourceId' => $ledger->id, 'resourceType' => 'Ledger'])` など)
       を埋め込む。
    4. 既存のワークフロー履歴タブと新しいタブが機能することを確認。

#### **ステップ 4: `ActivityHistoryDisplay` の「総合アクティビティ」対応強化**

* **目的**: レコード、台帳定義、フォルダの活動ログを単一のタブで表示できるようにする。
* **作業内容**:
    1. `app/Livewire/Common\ActivityHistoryDisplay.php` を修正。
    2. `mount()` メソッドで、`$resourceId`, `$resourceType` に加え、`$includeRelatedResources` (boolean)
       などのオプションを受け取るように変更。
    3. `$includeRelatedResources` が `true` の場合、対象レコードの `ledger_define_id` と `folder_id`
       を取得し、それらに関連するアクティビティも一緒に取得するロジックを追加。
        * `CustomActivity::where(function ($query) use ($ledgerId, $ledgerDefineId, $folderId) { ... })`
          のようにOR条件でクエリを構築。
    4. 各アクティビティの表示に、それがどのリソース（レコード、台帳定義、フォルダ）に対する操作かを明確に示すラベルを追加。

#### **ステップ 5: `Ledger/RecordsTable.php` への概要情報とモーダル導線追加**

* **目的**: 台帳一覧/検索画面に、フォルダ概要パネルと、各台帳定義ごとの概要情報および詳細への導線を追加する。
* **作業内容**:
    1. `app/Livewire/Common/FolderSummary.php` を作成。
    2. `resources/views/livewire/common/folder-summary.blade.php` を作成。
    3. `app/Livewire/Ledger/RecordsTable.php` を修正。
    4. `records-table.blade.php` の画面上部に `@livewire('common.folder-summary', ['folderId' => $currentFolderId])`
       を埋め込む。
    5. `FolderSummary` コンポーネント内で、現在のフォルダの権限概要（ログインユーザー視点）と、Activity / Permission
       詳細モーダルを開くボタンを実装。
        * モーダルは `ActivityHistoryDisplay` や `PermissionDisplay` を動的に読み込む形を検討。
    6. 各 `LedgerDefine` 行に、その定義の権限概要（アイコン等）と直近の活動概要（最終更新者、日時）を表示。
    7. 各 `LedgerDefine` 行に、Activity / Permission 詳細モーダルを開くボタンを実装。

---