# `app/Livewire/Ledger/Show.php` リファクタリング計画

## 1. 目的

`app/Livewire/Ledger/Show.php` コンポーネントが肥大化し、複数の責務を抱えている現状を改善し、コードの可読性、保守性、テスト容易性を向上させる。また、機能間の結合度を下げ、将来的な機能追加や変更を容易にする。

## 2. 現状の課題

`Show.php` は以下の複数の責務を担っているため、コードが複雑化している。

*   台帳レコードの基本情報の表示と管理
*   ワークフローの状態表示とアクション（承認申請、承認、差し戻しなど）の制御
*   台帳レコードの変更差分表示
*   添付ファイルの再処理機能
*   権限チェックロジック
*   表示レベルによるカラムのフィルタリング
*   グループ化されたカラムの開閉状態管理

## 3. リファクタリングの全体方針

責務の分離を徹底し、各機能を独立したLivewire子コンポーネントまたはサービスとして切り出す。コンポーネント間の連携はLivewireのイベントシステムを主軸とするイベント駆動型アーキテクチャを採用し、疎結合なシステムを構築する。

### 3.1. 責務の分離 (Single Responsibility Principle)

*   **Livewire コンポーネントの分割:**
    *   現在の `Show` コンポーネントを、より小さな Livewire コンポーネントに分割する。
    *   各コンポーネントは、それぞれの責務に特化したプロパティ、メソッド、ビューを持つようにする。
*   **ビジネスロジックのサービス化:**
    *   既存の `WorkflowService` をさらに活用し、差分計算ロジックや権限チェックロジックなど、Livewire コンポーネントから独立したビジネスロジックをサービスとして切り出す。

### 3.2. イベント駆動型アーキテクチャによる連携

*   各子コンポーネントは、自身の責務範囲内で完結する処理を行い、親コンポーネントや他の子コンポーネントに影響を与える必要がある場合は、Livewire のイベント (`$this->dispatch()`) を使用して通知する。
*   親コンポーネント (`Show`) は、これらのイベントをリッスンし、必要に応じて自身の状態を更新したり、他の子コンポーネントにデータを渡したりする。

### 3.3. コードの抽象化と再利用

*   **トレイトの活用:** 複数のLivewireコンポーネントで共通して使用されるロジックがあれば、トレイトとして抽出し、コードの重複を避ける。
*   **カスタム Blade コンポーネントの作成:** ビューファイル内で繰り返し使われるUIパターンがあれば、カスタム Blade コンポーネントとして切り出す。

### 3.4. パフォーマンスの考慮

*   **遅延ロード (Lazy Loading):** タブ切り替えで表示されるコンテンツなど、ユーザーが明示的に操作しないと見えない部分は遅延ロードを検討し、初期ロード時のパフォーマンスを向上させる。

## 4. 各機能の分割計画

### 4.1. `Show` コンポーネント (親コンポーネント) の責務

*   `LedgerRecord` のロードと管理（中心的なデータソース）
*   各子コンポーネントへの `LedgerRecord` および関連データの受け渡し
*   子コンポーネントからのイベントのリスニングと、それに応じた自身の状態更新や他の子コンポーネントへの通知
*   タブの切り替え管理
*   表示レベルの制御（`displayLevel` プロパティと `setDisplayLevel` メソッド）
*   グループ化されたカラムの開閉状態管理（`collapsedStates` プロパティと `toggleGroup` メソッド）

### 4.2. `WorkflowPanel` コンポーネント (Livewire子コンポーネント)

*   **責務:** ワークフローの状態表示、アクションボタンの制御、モーダルの表示、ワークフロー関連の権限チェック。
*   **切り出すプロパティ:** `approvalRequestModal`, `returnToDraftModal`, `selectedApproverId`, `showAssigneeModalForNext`, `nextAssigneeRoleType`, `approverOptions`, `returnComment`, `showCommentModal`, `actionTypeForModal`, `commentForModal`, `showAssigneeModal`, `assigneeModalRoleType`, `workflowHistory`, `requiredRolesProgress`
*   **切り出すメソッド:** `openApproverSelectModal()`, `handleAssigneeSelected()`, `getInitialApproverId()`, `openReturnToDraftModal()`, `loadApproverOptions()`, `approveTask()`, `openNextApproverSelectModal()`, `handleNextApproverSelected()`, `getInitialApproverIdExcludingSelfAndCurrent()`, `returnTaskToDraft()`, `canRequestApproval()`, `canApprove()`, `canReturnToDraft()`, `loadWorkflowHistory()`, `openCommentModal()`, `executeActionWithComment()`, `getCommentModalTitle()`, `getCommentModalActionLabel()`, `getCommentModalActionClass()`, `handleActionWithComment()`
*   **連携:** `LedgerRecord` をプロパティとして受け取る。ワークフローの状態変更時には、親の `Show` コンポーネントにイベントを発行し、`LedgerRecord` の再ロードや差分情報の更新をトリガーする。

### 4.3. `LedgerDiffViewer` コンポーネント (Livewire子コンポーネント)

*   **責務:** 台帳レコードの変更差分計算と表示。
*   **切り出すプロパティ:** `comparisonTargetDiff`, `contentChanges`, `hasChangedColumns`, `showChanges`
*   **切り出すメソッド:** `prepareContentDiff()`, `findComparisonTargetDiff()`
*   **連携:** `LedgerRecord` をプロパティとして受け取る。`Show` コンポーネントの「詳細」タブで、かつ差分表示が有効な場合にのみロードする（遅延ロードを検討）。`LedgerRecord` の内容が更新された場合、親からのイベントを受け取り、差分データを再計算する。

### 4.4. 添付ファイル処理のサービス化

*   **責務:** 添付ファイルの再処理ロジック。
*   **切り出すメソッド:** `retryProcessing()`
*   **連携:** `AttachedFile` モデルや専用のサービスに移動する。UIからの再試行アクションは、Livewire イベントを通じてこのサービスを呼び出す形にする。

## 5. `app/Livewire/Ledger/ShowDiff.php` との関係

`app/Livewire/Ledger/ShowDiff.php` は、台帳の**過去の**変更履歴（Diff）を個別に表示することに特化したコンポーネントであり、`Show.php` の「差分表示」機能とは異なる独立した役割を担っている。

*   **`ShowDiff.php` の役割:** 特定の `ledgerId` と `diffId` を受け取り、その時点の台帳データと添付ファイルの状態を再現して表示する。これは独立したページで過去のバージョンを閲覧するためのもの。
*   **`LedgerDiffViewer` との共通化:** `Show.php` から切り出す `LedgerDiffViewer` コンポーネントは、`ShowDiff.php` の表示ロジックの一部を参考にすることができる。特に、`ColumnDefine::normalizeArrayOrCollection()` の利用や、`content` と `content_attached` の処理、添付ファイルの再構築ロジックなどは、独立したサービス（例: `LedgerContentProcessor` や `LedgerDiffProcessor`）として切り出すことで、両方のコンポーネントから再利用でき、コードの重複を排除できる。
*   **データフローの明確化:**
    *   `Show.php` は常に最新の `Ledger` モデルを基点とし、`LedgerDiff` はその変更履歴として扱う。
    *   `ShowDiff.php` は、特定の `LedgerDiff` を基点として、その時点の `Ledger` の状態を再構築する。

このリファクタリングにより、`Show.php` はよりシンプルで管理しやすくなり、各機能が独立して開発・テストできるようになる。
