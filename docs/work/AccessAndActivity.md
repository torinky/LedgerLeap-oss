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

### **✅ ステップ 2: `App\Livewire\Common\PermissionDisplay` の基本実装 (完了)**

*   **目的**:
    *   特定のリソース（台帳レコード、台帳定義、フォルダ）に対するアクセス権限を視覚的に表示する汎用コンポーネントを実装する。
    *   ログインユーザーの権限概要、アクセス可能な組織・ロール・ユーザーのリストを、権限の由来（直接/継承）も区別して提示する。

*   **作業内容**:
    1.  **`app/Services/PermissionService.php` の作成と強化**:
        *   `getAccessRolesWithPermissions` メソッドを実装し、指定リソースにアクセス可能なロールとその権限を、権限の包含関係を考慮して取得するロジックを実装。
        *   `getAccessOrganizationsWithPermissions` メソッドを実装し、アクセス可能なロールを持つ組織を効率的に特定。各組織について、**直接のロールと継承されたロールを分類**して取得。また、組織名を**階層的に表示**するための `display_name` を生成。
        *   `getAccessUsers` メソッドを実装し、アクセス可能なユーザーを検索・ページネーション表示。各ユーザーの組織とロールをイーガーロードし、**ロールを「直接」と「組織から継承」に、権限も同様に分類**して `categorized_roles`, `categorized_permissions` プロパティとして追加。
        *   `getCurrentUserAllPermissions` メソッドを実装し、ログインユーザーが持つ全ての権限タイプを配列で取得。
    2.  **`app/Livewire/Common/PermissionDisplay.php` の作成**:
        *   `PermissionService` を利用して、アクセス可能なロール、組織、ユーザーのリスト、およびログインユーザーの全権限をプロパティとしてビューに提供。
        *   ユーザー検索用の `searchUserQuery` プロパティと、変更時にページネーションをリセットするロジックを実装。
    3.  **`resources/views/livewire/common/permission-display.blade.php` の作成**:
        *   daisyUI のセマンティックカラーと `x-mary-table` コンポーネントを使用して、UIの一貫性を確保。
        *   ログインユーザーが持つ全ての権限をバッジで表示。
        *   「アクセス権限を持つ組織」リストで、各組織の**階層的な組織名**と、その組織が持つ**直接/継承ロール**を区別して表示。
        *   「アクセス権限を持つロール」リストで、各ロールが持つ権限をバッジで表示。
        *   「アクセス可能なユーザー」リストで、各ユーザーの**所属組織**、および**直接/継承ロール**、**直接/継承権限**を区別して表示。
    4.  **`resources/views/livewire/common/permission-display-no-permission.blade.php` の作成**:
        *   権限がない場合に表示するビューを作成。
    5.  **翻訳ファイルの追加・修正**:
        *   `lang/ja/ledger.php` と `lang/ja/permission.php` に、権限表示コンポーネントで使用する新しい翻訳キーを追加・修正。

*   **動作確認**:
    *   テストルートを通じて、`PermissionDisplay` コンポーネントが様々なリソース（Folder, LedgerDefine, Ledger）に対して正しく表示されることを確認。
    *   ログインユーザーの権限表示が、保持する全ての権限タイプを反映していることを確認。
    *   組織リストで、階層的な組織名、直接/継承ロールが正しく表示されることを確認。
    *   ユーザーリストで、所属組織、直接/継承ロール、直接/継承権限が正しく表示されることを確認。
    *   権限の包含関係が考慮され、表示が簡潔になっていることを確認。
    *   ユーザー検索機能が正常に動作することを確認。

*   **成果物**: 権限の由来（直接/継承）や組織階層を考慮した、詳細かつ分かりやすい権限表示コンポーネントが完成した。これにより、管理者は複雑な権限設定を正確に把握でき、一般ユーザーは自身のアクセス範囲を容易に理解できるようになった。

---

### **✅ ステップ 3: `Ledger/Show.php` (レコード詳細画面) への共通コンポーネント統合 (完了)**

*   **目的**:
    *   ステップ1と2で作成した共通コンポーネント (`ActivityHistoryDisplay` と `PermissionDisplay`) を、台帳レコード詳細画面に新しいタブとして組み込む。
    *   `ActivityHistoryDisplay` に特定のカラムを非表示にするオプションを追加し、台帳詳細画面では「対象リソース」列を非表示にする。

*   **作業内容**:
    1.  **`app/Livewire/Common/ActivityHistoryDisplay.php` の修正**:
        *   `mount` メソッドに `$hiddenColumns` パラメータを追加。
        *   `render` メソッド内で、`$hiddenColumns` に基づいて表示するテーブルヘッダーを動的に生成する `getVisibleHeaders` メソッドを実装。
    2.  **`resources/views/livewire/common/activity-history-display.blade.php` の修正**:
        *   `x-mary-table` の `:headers` 属性に、PHP側から渡された `$headers` 変数をバインドするように修正。
    3.  **`app/Livewire/Ledger/Show.php` の修正**:
        *   Bladeテンプレートから `PermissionDisplay` コンポーネントに渡すための `currentUserAllPermissions` プロパティを追加。
    4.  **`resources/views/livewire/ledger/show.blade.php` の修正**:
        *   既存の `<x-mary-tabs>` コンポーネント内に、「総合活動履歴」と「アクセスと権限」のタブ (`<x-mary-tab>`) を追加。
        *   各タブ内に、対応する共通コンポーネントを `@livewire` ディレクティブで埋め込み。
        *   `ActivityHistoryDisplay` の呼び出し時に `'hiddenColumns' => ['subject']` を指定し、「対象リソース」列を非表示にした。
    5.  **`lang/ja/ledger.php` の修正**:
        *   新しいタブのラベル用の翻訳キー (`tab.activity_history`, `tab.access_and_permissions`) を追加。

*   **動作確認**:
    *   台帳詳細画面に「総合活動履歴」「アクセスと権限」タブが表示され、クリックで各コンポーネントが表示されることを確認。
    *   「総合活動履歴」タブで、「対象リソース」列が非表示になっていることを確認。
    *   「アクセスと権限」タブで、その台帳レコードに関連する権限情報（ログインユーザーの権限、アクセス可能な組織・ロール・ユーザー）が正しく表示されることを確認。
    *   既存の「詳細」タブや「ワークフロー履歴」タブの機能が影響を受けていないことを確認。

*   **成果物**: ユーザーが台帳詳細画面から、そのレコードに関する活動履歴と権限情報にシームレスにアクセスできる、統合されたUIが完成した。共通コンポーネントは、呼び出し元のコンテキストに応じて表示をカスタマイズできる柔軟性も備えている。

---
---

### **✅ ステップ 4: `Ledger/RecordsTable.php` への概要情報とモーダル導線追加 (完了)**

*   **目的**:
    *   台帳一覧／検索画面 (`RecordsTable`) に、現在表示しているフォルダや各台帳定義の権限・活動状況の概要を提示する。
    *   概要情報から、詳細情報コンポーネント (`ActivityHistoryDisplay`, `PermissionDisplay`) をモーダルで呼び出し、ユーザーが効率的に情報を確認できるようにする。

*   **作業内容**:
    1.  **`app/Livewire/Ledger/RecordsTable.php` の修正**:
        *   モーダルウィンドウの表示状態を制御するプロパティ (`$showPermissionModal`, `$showActivityModal`) と、モーダルに渡すリソース情報を格納するプロパティ (`$modalTitle`, `$modalResourceId`, `$modalResourceType`) を追加。
        *   モーダルを開くための `openPermissionModal()` と `openActivityModal()` メソッドを実装。これらのメソッドは、ビューのボタンから `wire:click` で呼び出される。
        *   `render()` メソッド内で `PermissionService` を利用し、現在表示中のフォルダ (`$currentFolder`) に対するログインユーザーの最高権限を取得し、ビューに渡すロジックを追加。
    2.  **`resources/views/livewire/ledger/records-table.blade.php` の修正**:
        *   画面上部（パンくずリストの下）に、現在のフォルダ名、ログインユーザーの権限概要、そして詳細モーダルを開くための「権限詳細」「活動履歴」ボタンを含む**フォルダ概要パネル**を追加。
        *   ファイルの末尾に、権限表示用と活動履歴表示用の `<x-mary-modal>` を2つ配置。モーダルは Livewire プロパティにバインドされ、内部で `@livewire` ディレクティブを使用して共通コンポーネントを動的に読み込む。
    3.  **`resources/views/components/ledgerDefine/header.blade.php` の修正**:
        *   各台帳定義のヘッダー部分に、その台帳定義に対する「権限詳細」「活動履歴」ボタンを追加。
        *   これらのボタンも、`RecordsTable` コンポーネントの `openPermissionModal()` / `openActivityModal()` メソッドを呼び出す。

*   **動作確認**:
    *   台帳一覧画面で、画面上部に現在のフォルダの概要パネルが表示され、「権限詳細」「活動履歴」ボタンをクリックすると、そのフォルダを対象としたモーダルが表示されることを確認。
    *   各台帳定義のヘッダー部分にあるボタンをクリックすると、その台帳定義を対象としたモーダルが表示されることを確認。
    *   モーダル内で `PermissionDisplay` および `ActivityHistoryDisplay` コンポーネントが正しく表示され、機能することを確認。

*   **成果物**: 台帳一覧画面が、単なるレコードの入り口だけでなく、フォルダや台帳定義といった上位階層の情報を確認するためのハブとしても機能するようになった。ユーザーは個別の詳細画面に遷移することなく、必要な情報をモーダルで素早く確認できるようになった。

---

---

#### **ペルソナA: 管理者 / 部門長 (佐藤 健太)**

*   **目標**: 情報セキュリティ確保、コンプライアンス、監査対応、問題究明。

##### **シナリオ1: フォルダ単位での監査**
*   **状況**: 「『2024年度_重要プロジェクト』フォルダに関して、誰がいつ、どのような操作を行ったか、**このフォルダに関連する全ての活動**を時系列で確認したい。」
*   **期待する情報**:
    *   **フォルダ自体の変更**: フォルダ名の変更、フォルダの移動、権限設定の変更など。
    *   **配下の台帳定義の変更**: このフォルダ内に新しい台帳定義が作成された、既存の定義が変更された、など。
    *   **配下の台帳レコードの操作**: このフォルダ内の台帳で、新しいレコードが作成された、既存のレコードが更新・承認・削除された、など。
    *   **不要な情報**: このフォルダの**親フォルダ**や**兄弟フォルダ**の活動履歴。これらはノイズになる。
*   **結論**:
    *   `ActivityHistoryDisplay` を `resourceType: 'Folder'` で呼び出した場合、そのフォルダ自身と、**そのフォルダおよび全ての子孫フォルダに属する**台帳定義・台帳レコードの活動履歴を全て表示すべき。

##### **シナリオ2: 台帳定義単位での監査**
*   **状況**: 「『製品仕様書』という台帳定義について、定義自体の変更履歴と、**この仕様書に基づいて作成された全てのレコード**に対する操作（誰が作成し、誰が承認したかなど）をまとめて確認したい。」
*   **期待する情報**:
    *   **台帳定義自体の変更**: カラムの追加・削除、ワークフロー設定の変更など。
    *   **この定義に紐づく全レコードの操作**: レコードの作成、更新、承認、差し戻し、削除など。
    *   **不要な情報**: この台帳定義が属するフォルダの変更履歴や、同じフォルダ内の他の台帳定義に関する活動履歴。
*   **結論**:
    *   `ActivityHistoryDisplay` を `resourceType: 'LedgerDefine'` で呼び出した場合、その台帳定義自身と、**その台帳定義に紐づく全ての**台帳レコードの活動履歴を表示すべき。

##### **シナリオ3: 個別レコードの詳細調査**
*   **状況**: 「特定の顧客情報レコード（ID: 123）について、誰が閲覧し、誰が編集し、誰が承認したのか、このレコードに関する全ての履歴を時系列で追跡したい。」
*   **期待する情報**:
    *   **台帳レコード自身の操作**: レコードの作成、更新、承認、差し戻し、削除など。
    *   **関連する親リソースの変更**: このレコードの挙動に影響を与えた可能性のある、親である台帳定義やフォルダの変更履歴も同時に確認できると、原因究明の際に役立つことがある。（例: 「レコードが編集できなくなった」→「親フォルダの権限が変更されていた」）。
*   **結論**:
    *   `ActivityHistoryDisplay` を `resourceType: 'Ledger'` で呼び出した場合（台帳詳細画面のタブ）、その台帳レコード自身の活動履歴に加え、**親である台帳定義とフォルダの活動履歴も**含めて表示するのが望ましい。これは、現在の `includeRelatedResources: true` の挙動が、このシナリオには合致していることを意味します。

---

#### **ペルソナB: 実務担当者 / 現場リーダー (田中 美咲)**

*   **目標**: 業務の進捗確認、過去の作業内容の確認。

##### **シナリオ4: 担当フォルダの状況把握**
*   **状況**: 「自分が担当している『品質管理記録』フォルダで、最近誰がどんな作業をしたかざっと確認したい。新しい記録は誰が追加した？承認待ちは誰が処理した？」
*   **期待する情報**:
    *   管理者（佐藤）のシナリオ1と同様、このフォルダ配下で発生したレコードの作成・更新・承認などの活動履歴。フォルダ自体の設定変更にはあまり関心がない。
*   **結論**:
    *   管理者と同じく、`resourceType: 'Folder'` で呼び出した場合は、そのフォルダ配下の全ての活動履歴が表示されることが望ましい。

---

### **ステップ5の作業内容の再定義**

上記の深掘り検討に基づき、ステップ5の作業内容を以下のように具体的に再定義します。

**`app/Livewire/Common/ActivityHistoryDisplay.php` の `getActivitiesQuery()` メソッドの修正ロジック:**

1.  **`resourceType` が `'Folder'` の場合**:
    *   `Folder::descendantsAndSelf($this->resourceId)->pluck('id')` を使って、対象フォルダとその全ての子孫フォルダのIDリストを取得する。
    *   `subject_type` が `Folder` で `subject_id` がこのIDリストに含まれるログを取得。
    *   `subject_type` が `LedgerDefine` で、その `folder_id` がこのIDリストに含まれるログを取得。
    *   `subject_type` が `Ledger` で、その `Ledger` の `define->folder_id` がこのIDリストに含まれるログを取得。
    *   これらの条件を `OR` で結合する。
    *   `includeRelatedResources` はこのモードでは使用しない（常に子孫を含める）。

2.  **`resourceType` が `'LedgerDefine'` の場合**:
    *   `subject_type` が `LedgerDefine` で `subject_id` が `$this->resourceId` であるログを取得。
    *   `subject_type` が `Ledger` で、その `ledger_define_id` が `$this->resourceId` であるログを取得。
    *   これらの条件を `OR` で結合する。
    *   `includeRelatedResources` はこのモードでは使用しない。

3.  **`resourceType` が `'Ledger'` の場合**:
    *   `subject_type` が `Ledger` で `subject_id` が `$this->resourceId` であるログを取得。
    *   **かつ、`$this->includeRelatedResources` が `true` の場合**:
        *   親の `LedgerDefine` のログ（`subject_type` = `LedgerDefine`, `subject_id` = `ledger->ledger_define_id`）を取得。
        *   親の `Folder` のログ（`subject_type` = `Folder`, `subject_id` = `ledger->define->folder_id`）を取得。
    *   これらの条件を `OR` で結合する。

---

#### **ステップ 5: アクティビティログの表示範囲の最適化**

*   **目的**: `ActivityHistoryDisplay` コンポーネントで、各リソース（フォルダ、台帳定義）の活動履歴を表示する際に、ユーザーの期待に沿った範囲のログのみを表示するように修正する。
*   **作業内容**:
    1.  **`app/Livewire/Common/ActivityHistoryDisplay.php` の修正**:
        *   `getActivitiesQuery()` メソッド内のロジックを修正。
        *   `resourceType` が `'Folder'` の場合:
            *   そのフォルダ自身 (`subject_type` = `Folder`, `subject_id` = `$resourceId`) のアクティビティログを取得する。
            *   そのフォルダに直接属する `LedgerDefine` のアクティビティログを取得する。
            *   そのフォルダに属する `LedgerDefine` から作成された `Ledger` のアクティビティログを取得する。
            *   `includeRelatedResources` は、この文脈では「子孫フォルダの活動履歴を含めるか」というオプションとして再定義するか、一旦このステップでは無視する。
        *   `resourceType` が `'LedgerDefine'` の場合:
            *   その台帳定義自身 (`subject_type` = `LedgerDefine`, `subject_id` = `$resourceId`) のアクティビティログを取得する。
            *   その台帳定義から作成された全ての `Ledger` (`subject_type` = `Ledger`) のアクティビティログを取得する。
            *   `includeRelatedResources` は、この文脈では「親フォルダの活動履歴を含めるか」というオプションになるが、ユーザーの期待に反するため、`false` の挙動（親を含めない）をデフォルトとする。
    2.  **`resources/views/livewire/ledger/records-table.blade.php` の修正**:
        *   台帳定義の活動履歴モーダルを呼び出す際に、`includeRelatedResources` を `false` に設定する（または削除する）。
    3.  **動作確認**:
        *   フォルダの活動履歴モーダルで、そのフォルダと配下の台帳定義・レコードのログのみが表示されることを確認。
        *   台帳定義の活動履歴モーダルで、その台帳定義と、それから作成されたレコードのログのみが表示されることを確認。

---

#### **ステップ 6: 表示の冗長性の解消**

*   **目的**: フォルダや台帳定義の活動履歴モーダルで、自明な「対象リソース」列を非表示にする。
*   **作業内容**:
    1.  **`resources/views/livewire/ledger/records-table.blade.php` の修正**:
        *   `openActivityModal` を呼び出すボタンの `wire:click` イベントを修正し、`ActivityHistoryDisplay` に `'hiddenColumns' => ['subject']` を渡すようにする。
        *   フォルダの活動履歴モーダルと台帳定義の活動履歴モーダルの両方でこの設定を適用する。
    2.  **動作確認**:
        *   フォルダおよび台帳定義の活動履歴モーダルを開いた際に、「対象リソース」列が表示されていないことを確認。
        *   `/notifications` 画面の「活動履歴」タブでは、引き続き「対象リソース」列が表示されていることを確認。

---

#### **ステップ 7: フィルタリング機能の実装（MVP）**

*   **目的**: `ActivityHistoryDisplay` と `PermissionDisplay` に、主要なフィルタリング機能を追加する。
*   **作業内容**:
    1.  **`app/Livewire/Common/ActivityHistoryDisplay.php` の修正**:
        *   フィルタリング用の public プロパティ (`$filterCauserName`, `$filterEventType`, `$filterStartDate`, `$filterEndDate`) を追加。
        *   `getActivitiesQuery()` メソッド内で、これらのプロパティが設定されている場合に `where` 句を追加するロジックを実装。
    2.  **`resources/views/livewire/common/activity-history-display.blade.php` の修正**:
        *   テーブルの上部に、操作者や操作タイプを選択する `x-mary-select` や、日付を選択する `x-mary-datepicker` などのフィルタ入力UIを配置。
    3.  **`app/Livewire/Common/PermissionDisplay.php` の修正**:
        *   フィルタリング用の public プロパティ (`$filterRoleId`, `$filterPermissionType`) を追加。
        *   `getAccessRolesProperty` や `getAccessUsersProperty` などのプロパティ内で、これらのフィルタを適用するロジックを実装。
    4.  **`resources/views/livewire/common/permission-display.blade.php` の修正**:
        *   各リストの上部に、ロール名や権限タイプで絞り込むための `x-mary-select` などのフィルタ入力UIを配置。
    5.  **動作確認**:
        *   各コンポーネントで、フィルタUIが正しく表示され、選択した条件でリストの内容が絞り込まれることを確認。
        *   フィルタをリセットするボタンも追加し、動作を確認。

---

### **今後の課題**

*   **フィルタリング機能の強化**:
    *   **権限タイプによる絞り込み**: 特定の権限（例: 「承認権限」を持つロール・組織・ユーザー）のみを表示するフィルタ機能。
    *   **組織/ロール名による絞り込み**: 組織名またはロール名でリストをフィルタリングする機能。
    
*   **UI/UXの改善**:
    *   **ユーザーリストの所属組織名表示**: 組織名を親組織からのフルパス（例: `本社 > 営業部 > 東日本営業部`）で表示し、ツールチップや省略記法 (`...`) を活用してテーブルの幅を取りすぎないようにする工夫。

*   **表示されるアクティビティログの範囲**:
    *   **現状**: 台帳定義の活動履歴モーダルで `includeRelatedResources` を `true` にすると、その台帳定義が属する「フォルダ」の変更履歴も表示されてしまう。
    *   **ユーザーの期待**: ユーザーは「台帳定義」の活動履歴を見たい場合、その定義自体の変更履歴に加え、「その定義で作成された全レコード」に対する操作（作成、更新、承認など）が表示されることを期待する可能性が高い。フォルダ自体の変更履歴はノイズになりうる。
    *   **再検討事項**: ペルソナのシナリオに立ち返り、各リソース（フォルダ、台帳定義）のアクティビティ履歴モーダルで表示すべきログの範囲（`subject_type` と `subject_id`）を再定義する必要がある。`includeRelatedResources` の挙動を見直すか、より柔軟なフィルタリングオプションを設けるか検討する。

*   **表示の冗長性**:
    *   **現状**: フォルダの活動履歴モーダルで、`ActivityHistoryDisplay` に「対象リソース」列が表示され、全て同じフォルダ名になるため冗長。
    *   **ユーザーの期待**: 特定のリソースの活動履歴を見ている場合、そのリソース名は自明なので非表示にしたい。ただし、関連リソース（例: 子フォルダや台帳定義）のログも表示する場合は、この列は必要になる。
    *   **再検討事項**: フォルダの活動履歴モーダルで `hiddenColumns` を使って「対象リソース」列を非表示にする。また、「上位フォルダの活動履歴を含めるか」というオプション（現在の `includeRelatedResources` とは逆の方向）が必要かどうかも検討する。

---
