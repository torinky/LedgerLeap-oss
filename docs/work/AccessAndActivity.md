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

### **✅ ステップ 1.5: `UserActivityLog` の削除と `ActivityHistoryDisplay` への統合、`NotificationList` からのActivity Log表示ロジックの共通化 (完了)**

*   **目的**:
    *   重複するアクティビティログ表示ロジックを `ActivityHistoryDisplay` に集約し、`UserActivityLog` コンポーネントを削除する。
    *   `NotificationList` コンポーネント内でActivity Logの変更差分および表示メッセージを `ActivityLogFormatter` を使用して共通化する。
    *   これにより、コードの保守性を高め、UI/UXの一貫性を確保する。

*   **作業内容**:
    1.  **`app/Helpers/ActivityLogFormatter.php` の機能拡張**:
        *   `ActivityHistoryDisplay` の `getOperationDescription`, `getSubjectNameForDisplay`, `getRelatedEntityNameForDisplay`, `formatChanges`, `formatComment`, `getSubjectDisplay`, `getCauserDisplayName`, `getSubjectDetailLink`, `getCauserDetailLink` メソッドを**静的メソッド**としてここに全て移動・実装。
        *   これらのメソッドは、`CustomActivity` モデルインスタンスまたは通知ペイロードのような**配列**を引数として受け取り、適切な整形済み文字列やHTML、リンクを返すように汎用化。
        *   `formatChanges` メソッドは、DaisyUIのテキストカラークラスを適用し、変更を視覚的に分かりやすく表現。JSONカラムやリレーション関連の変更も適切に処理。
    2.  **`app/Livewire/Common/ActivityHistoryDisplay.php` の修正**:
        *   コンポーネント自身のメソッド (`getOperationDescription` など) を全て削除し、`ActivityLogFormatter::` で静的呼び出しする形に変更。コンポーネントはデータの取得とビューへの受け渡しに専念。
        *   `paginationTheme` を `mary` に明示的に設定。
    3.  **`app/Livewire/Notifications/NotificationList.php` の修正**:
        *   `App\Helpers\ActivityLogFormatter` を `use` 宣言に追加。
        *   `formatNotificationData` メソッド内で、通知データの `payload` からActivity Log関連の情報を抽出し、**`ActivityLogFormatter::` の静的メソッド群を呼び出してメッセージ、リンク、コメント、整形済み変更差分を生成するように変更**。
        *   これにより、Activity Logに基づく通知とその他の通知の両方で、一貫した表示ロジックが適用されるようになった。
        *   変更差分の表示は `changes_formatted` プロパティを利用。
    4.  **`resources/views/notifications/index.blade.php` の修正**:
        *   `@livewire('user-activity-log')` の呼び出しを `@livewire('common.activity-history-display')` に置き換え。
        *   `activity` タブの `aria-label` を `__('ledger.activity.title')` に修正。
    5.  **`resources/views/livewire/notifications/notification-list.blade.php` の修正**:
        *   変更差分表示部分で、`$display['changes_formatted']` を直接出力するように変更。
        *   変更履歴のタイトルを `__('ledger.activity.column.changes')` に統一。
    6.  **翻訳ファイルの統合と追加**:
        *   `lang/ja/ledger.php` に、`ActivityLogFormatter` で使用される新しい翻訳キー（リレーション操作に関するもの、複雑なデータ、不明なロール/フォルダなど）を全て追加・修正。
        *   `lang/ja/activitylog.php` は削除。
    7.  **`UserActivityLog` および `x-diff-display` コンポーネントの削除**:
        *   `app/Livewire/UserActivityLog.php` および `resources/views/livewire/user-activity-log.blade.php` を削除。
        *   `resources/views/components/diff-display.blade.php` を削除（他の場所での利用がないことを確認済み）。

*   **動作確認**:
    *   **`/notifications` 画面**:
        *   「活動履歴」タブでシステム全体のActivity Logが正しく表示され、`UserActivityLog` の機能を完全に代替していることを確認。
        *   「通知」タブで、各種通知（特に台帳更新、ワークフロー関連、リレーション操作などActivity Logに関連するもの）が、`ActivityLogFormatter` を通して整形されたメッセージ、リンク、コメント、変更差分で表示されることを確認。
        *   Activity Logベースではない通知（例: `WorkflowSummaryNotification`）も引き続き正しく表示されることを確認。
    *   **`/test-activity` 画面 (Ledger/LedgerDefine/Folderのアクティビティ)**:
        *   絞り込み表示と全件表示の両方で Activity Log が正しく表示され、`ActivityLogFormatter` が正しく適用されていることを確認。
        *   変更差分の表示、操作内容の表示、リンクの生成が `NotificationList` と同じく一貫した品質で表示されることを確認。
        *   権限がないユーザーでアクセスした場合、権限がない旨のメッセージが表示されることを確認。
    *   **全体的なパフォーマンス**: Activity Logの表示がパフォーマンスに大きな影響を与えないことを確認。

*   **成果物**: Activity Logの表示に関する全てのロジックが `ActivityLogFormatter` ヘルパークラスに集約され、`ActivityHistoryDisplay` と `NotificationList` の両方から再利用されるようになった。これにより、コードの重複が解消され、保守性およびUI/UXの一貫性が大幅に向上した。`UserActivityLog` コンポーネントは廃止された。

---

### **ステップ 2: `App\Livewire\Common\PermissionDisplay` の基本実装**

このステップでは、特定のリソース（台帳レコード、台帳定義、フォルダ）に対するアクセス権限を視覚的に表示する汎用コンポーネントを実装します。

*   **目的**:
    *   `$resourceId` と `$resourceType` を受け取り、指定されたリソース、またはその親階層のリソースに適用される権限情報を取得し、表示する。
    *   ログインユーザーの権限概要、関連するロールと権限タイプ、アクセス可能なユーザー/ロールの一覧を提示する。

*   **作業内容**:

    1.  **`app/Livewire/Common/PermissionDisplay.php` の作成**:
        *   `$resourceId`, `$resourceType` プロパティを受け取るように定義します。
        *   `mount()` メソッドで、これらのプロパティを初期化します。
        *   ログインユーザーの権限概要（例: 「あなたは閲覧権限があります」）を判定し、表示するためのプロパティを準備します。
        *   指定されたリソース、およびその親階層のフォルダや台帳定義から、関連するロールとそれらの持つアクセス権限 (`RoleFolderPermission`) を取得するロジックを実装します。
            *   `Folder` モデルの `getAllPermissionsWithInheritance` や `UserService::hasFolderPermission` など、既存の権限関連メソッドを活用します。
            *   `Ledger` の場合は `define` を介して `folder` の権限を取得し、`LedgerDefine` の場合は自身の権限と `folder` の権限を取得します。
            *   `RoleFolderPermission` の `permission` カラムは `FolderPermissionType` Enum であるため、その `getLabel()` や `getColor()` を利用して表示します。
        *   アクセス可能な**ユーザー/ロールの一覧**を取得します。（これは `UserService::getUsersWithFolderPermission` を利用できるかもしれません。）
        *   ページネーションを実装します。

    2.  **`resources/views/livewire/common/permission-display.blade.php` の作成**:
        *   ログインユーザーの権限概要を画面上部に簡潔に表示します。
        *   階層的な権限元（例: 「この権限は親フォルダ `XXX` から継承されています」）を視覚的に表示します。
        *   ロールごとの権限一覧をテーブル形式で表示します。
            *   ロール名、権限タイプ（アイコンやバッジで `READ`, `WRITE`, `INSPECT`, `APPROVE`, `ADMIN` を表示）。
            *   `FolderPermissionType` の `getLabel()` や `getColor()` を利用して表示。
        *   アクセス可能なユーザー/ロールの一覧をテーブルまたはリスト形式で表示します。
        *   `mary` UI のページネーションコンポーネントを使用します。

    3.  **翻訳ファイルの追加・修正**:
        *   `lang/ja/ledger.php` に、`PermissionDisplay` で使用する新しい翻訳キー（例: `permission_overview`, `inherited_from`, `roles_with_access`, `users_with_access`、各権限タイプのラベルなど）を追加・修正します。

---

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