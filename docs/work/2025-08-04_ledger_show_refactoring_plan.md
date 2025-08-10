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

## 5. サービス層の再設計と責務の明確化

既存のサービス (`LedgerService`, `WorkflowService`) を調査した結果、リファクタリング計画をより具体的にする。

### 5.1. 新規作成するサービス

*   **`LedgerContentProcessor` サービス:**
    *   **責務:** 台帳の `content` と `column_define` を解釈し、表示用に整形するロジックを担当する。これには、`Show.php` の `render()` 内や `ShowDiff.php` の `loadDiffRecord()` 内の状態再現ロジックが含まれる。
    *   **理由:** 表示データの整形は、ワークフローや台帳の基本操作とは独立した関心事であり、専用のサービスに切り出すことで責務が明確になる。

*   **`LedgerDiffProcessor` サービス:**
    *   **責務:** 2つの台帳状態を比較し、変更差分を計算するロジック (`prepareContentDiff`, `findComparisonTargetDiff`) を担当する。
    *   **理由:** 差分計算は複雑で独立したロジックであるため、専用サービス化が望ましい。

### 5.2. 既存サービスの拡張とモデルへの責務移管

*   **`WorkflowService` (拡張):**
    *   **責務:** 現在のワークフロー状態遷移ロジックに加え、`Show.php` に存在する権限チェックロジック (`canRequestApproval`, `canApprove`, `canReturnToDraft` など) を移管する。これにより、ワークフローに関するビジネスルールを一元管理する。
    *   **理由:** 権限チェックはワークフローのコアロジックと密接に関連しており、サービスに含めることで凝集度が高まる。

*   **`AttachedFile` モデル (責務移管):**
    *   **責務:** `Show.php` の `retryProcessing` メソッドのロジックを、`AttachedFile` モデル自身のメソッド（例: `retryProcessing()`) として実装する。
    *   **理由:** ファイルの再処理は、`AttachedFile` モデルインスタンスに対する操作であり、モデル自体にメソッドとして持たせるのが最も自然な設計である。

## 6. 段階的なリファクタリング手順

リファクタリングは以下のステップで段階的に進める。各ステップの完了ごとに動作確認とテストを行い、安全性を確保する。

### Step 0: テストハーネスの構築 (最優先)

1.  **`ShowTest.php` の作成:** `tests/Feature/Livewire/Ledger/ShowTest.php` を新規作成する。
2.  **基本テストの実装:**
    *   コンポーネントが正常にマウントされること。
    *   必要なデータ (`LedgerRecord`, `LedgerDefineRecord`) がロードされること。
    *   台帳名などが表示されることを確認する。
3.  **ワークフロー状態に応じたテスト:**
    *   台帳のステータス (`DRAFT`, `PENDING_INSPECTION`, `PENDING_APPROVAL`, `APPROVED`) ごとに、適切なアクションボタンが表示/非表示になることを検証するテストケースを追加する。
4.  **テストの実行と安定化:** 作成したテストが安定してパスすることを確認する。これがリファクタリングの安全網となる。

#### Step 0.1: `ShowTest.php` の現状と不足しているテスト

`tests/Feature/Livewire/Ledger/ShowTest.php` は既に存在し、以下の基本的なテストケースが実装されています。

**実装済みのテスト:**

*   **基本的なレンダリングとデータロード:**
    *   `component_renders_successfully()`: コンポーネントがレンダリングされ、台帳名が表示されることを確認。
    *   `it_loads_ledger_record_on_mount()`: `mount`時に`ledgerRecord`と`ledgerDefineRecord`が正しくロードされることを確認。
*   **ワークフローの状態に応じたボタンの表示/非表示:**
    *   `it_shows_correct_buttons_when_status_is_pending_inspection()`: `PENDING_INSPECTION`ステータスでのボタン表示を検証。
    *   `it_shows_correct_buttons_when_status_is_pending_approval()`: `PENDING_APPROVAL`ステータスでのボタン表示を検証。
    *   `it_shows_no_workflow_buttons_when_status_is_approved()`: `APPROVED`ステータスでのボタン表示を検証。

しかし、`Show.php` の以下の機能については、まだテストが不足しています。これらはリファクタリングの安全性を確保するために、Step 0の完了前に実装する必要があります。

**未実装のテスト（追加が必要な項目）:**

1.  **台帳レコードの変更差分表示 (`prepareContentDiff`, `findComparisonTargetDiff`):**
    *   コンテンツが変更された場合の`prepareContentDiff`のテスト。
    *   コンテンツが変更されていない場合の`prepareContentDiff`のテスト。
    *   カラムが追加/削除された場合の`prepareContentDiff`のテスト。
    *   関連する差分が見つかる場合の`findComparisonTargetDiff`のテスト。
    *   関連する差分が見つからない場合の`findComparisonTargetDiff`のテスト（例：以前の差分がない、コンテンツが同一）。

2.  **添付ファイルの再処理機能 (`retryProcessing`):**
    *   `retryProcessing`がジョブを正常にディスパッチし、ステータスを更新することのテスト。
    *   `retryProcessing`が`attachedFileId`が存在しない場合を適切に処理することのテスト。

3.  **権限チェックロジック (`canRequestApproval`, `canApprove`, `canReturnToDraft`):**
    *   様々なユーザーロールと台帳の状態の下で`canRequestApproval()`を直接テスト。
    *   様々なユーザーロールと台帳の状態の下で`canApprove()`を直接テスト。
    *   様々なユーザーロールと台帳の状態の下で`canReturnToDraft()`を直接テスト。
    *   （注：これらのメソッドは`WorkflowService`に移動される予定のため、現時点で`ShowTest`でテストすることでカバレッジを確保し、後で`WorkflowService`専用のテストが必要になります。）

4.  **表示レベルによるカラムのフィルタリング (`setDisplayLevel`, `displayLevel`):**
    *   `setDisplayLevel`が`displayLevel`を正しく更新することのテスト。
    *   `render()`が`displayLevel`に基づいてカラムをフィルタリングすることのテスト。

5.  **グループ化されたカラムの開閉状態管理 (`toggleGroup`, `collapsedStates`):**
    *   `toggleGroup`が状態を正しく切り替えることのテスト。
    *   `mount()`で`collapsedStates`が正しく初期化されることのテスト。

これらのテストがすべて実装され、安定してパスすることを確認できれば、Step 0は完了とみなされます。

### Step 1: サービス層とモデルの抽出 (下準備)

1.  **`LedgerContentProcessor` サービスの作成と適用:**
    *   計画通りサービスを作成し、`Show.php` と `ShowDiff.php` の表示ロジックを置き換える。
2.  **`LedgerDiffProcessor` サービスの作成と適用:**
    *   計画通りサービスを作成し、`Show.php` の差分計算ロジックを置き換える。
3.  **`WorkflowService` への権限チェックロジック移管:**
    *   `Show.php` の `can...` で始まるメソッド群を `WorkflowService` に移動し、`Show.php` からはサービスを呼び出すように変更する。
4.  **`AttachedFile` モデルへの再処理ロジック移管:**
    *   `Show.php` の `retryProcessing` ロジックを `AttachedFile` モデルに移動する。

### Step 2: `WorkflowPanel` コンポーネントの分離

1.  `app/Livewire/Ledger/WorkflowPanel.php` を作成する。
2.  計画に基づき、ワークフロー関連のプロパティとメソッドを `Show.php` から `WorkflowPanel.php` に移動する。
3.  UIを `workflow-panel.blade.php` に切り出し、`Show.php` のビューに `<livewire:ledger.workflow-panel ...>` を組み込む。
4.  コンポーネント間の連携をイベント (`$dispatch`) で行うように実装する。

### Step 3: `LedgerDiffViewer` コンポーネントの分離

1.  `app/Livewire/Ledger/LedgerDiffViewer.php` を作成する。
2.  差分表示関連のロジックを移動し、`LedgerDiffProcessor` サービスを利用するように実装する。
3.  UIを `ledger-diff-viewer.blade.php` に切り出し、`Show.php` のビューに `<livewire:ledger.ledger-diff-viewer ...>` を組み込む。遅延ロード (`lazy`) の適用を検討する。

### Step 4: `Show` 親コンポーネントのクリーンアップ

1.  子コンポーネントに移動したプロパティ、メソッドを `Show.php` から完全に削除する。
2.  イベントリスナーを整理し、責務が「データ管理と子コンポーネントの統括」に限定されていることを最終確認する。

## 7. 検討事項

*   **テストの拡充:** リファクタリングと並行して、各コンポーネントとサービスのユニットテスト、フィーチャーテストを継続的に作成・拡充する。
*   **モーダルコンポーネントの共通化:** `WorkflowPanel` で使用する担当者選択モーダルやコメント入力モーダルは、他の箇所でも利用される可能性があるため、より汎用的なコンポーネントとして設計することを検討する。
*   **パフォーマンス:** `LedgerDiffViewer` の遅延ロードは、初期表示パフォーマンスの観点から積極的に採用すべきか。

このリファクタリングにより、`Show.php` はよりシンプルで管理しやすくなり、各機能が独立して開発・テストできるようになる。
