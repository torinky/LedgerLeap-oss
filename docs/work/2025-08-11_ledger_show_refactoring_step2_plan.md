# `app/Livewire/Ledger/Show.php` リファクタリング計画 - Step 2: WorkflowPanel コンポーネントの分離

## 1. 目的

`Show.php` から、ワークフローの状態表示、アクションボタン、関連モーダルといった、ワークフローに特化したUIとそのロジックを、新しいLivewire子コンポーネント `WorkflowPanel` に分離します。これにより、親コンポーネントの責務をさらに軽減し、ワークフロー関連のUIを独立して管理・開発できるようにします。

## 2. 詳細な作業計画

1.  **`WorkflowPanel` コンポーネントの作成:**
    *   `php artisan make:livewire Ledger/WorkflowPanel` コマンドを実行し、`WorkflowPanel.php` と `workflow-panel.blade.php` を生成します。

2.  **プロパティとメソッドの移管:**
    *   `Show.php` から、ワークフローに関連する全てのプロパティ（例: `approvalRequestModal`, `workflowHistory`, `requiredRolesProgress` など）とメソッド（例: `openApproverSelectModal`, `approveTask`, `returnTaskToDraft` など）を `WorkflowPanel.php` に移管します。
    *   `WorkflowPanel.php` は、親から渡される `LedgerRecord` を受け取るための `public Ledger $ledgerRecord;` プロパティを定義します。
    *   必要なサービス（`WorkflowService` など）をコンストラクタまたは `mount` メソッドで依存注入します。

3.  **ビューの分離:**
    *   `show.blade.php` から、ワークフローの状態表示エリア、アクションボタン、関連するモーダル（担当者選択、コメント入力）のコードを `workflow-panel.blade.php` に移管します。
    *   `show.blade.php` の元の場所には、`<livewire:ledger.workflow-panel :ledgerRecord="$ledgerRecord" wire:key="workflow-panel-{{ $ledgerRecord->id }}" />` のようにコンポーネントを埋め込みます。`wire:key` を指定し、親のデータが更新された際にコンポーネントが確実に再レンダリングされるようにします。

4.  **コンポーネント間の連携（イベント駆動）:**
    *   `WorkflowPanel` で承認や差し戻しなどのアクションが実行され、台帳の状態が変化した後、親コンポーネントにその旨を通知する必要があります。
    *   アクションの成功時に `WorkflowPanel` から `$this->dispatch('workflowUpdated');` のようにイベントを発行します。
    *   親である `Show.php` は、`#[On('workflowUpdated')]` アトリビュートを使ってこのイベントをリッスンするメソッドを定義します。
    *   イベントを受け取ったメソッド内で、`$this->ledgerRecord->refresh()` を実行し、最新の台帳情報を再読み込みします。また、差分表示など、他の関連データも必要に応じて更新します。

5.  **テストの作成と実行:**
    *   **新規作成:** `tests/Feature/Livewire/Ledger/WorkflowPanelTest.php` を作成し、`WorkflowPanel` コンポーネントの単体での動作を検証します。
        *   異なるステータスの `LedgerRecord` を渡した際に、適切なボタンが表示/非表示になること。
        *   ボタンクリックで、対応するモーダルが表示されること。
        *   アクション実行後に `workflowUpdated` イベントが正しく発行されること。
    *   **既存テストの修正:** `ShowTest.php` を修正し、`WorkflowPanel` コンポーネントが正しく読み込まれていること、そして `workflowUpdated` イベント受信後に `Show` コンポーネントの状態が正しく更新されることを検証します。

## 3. 期待される成果物

*   ワークフロー関連のUIとロジックがカプセル化された、再利用可能な `WorkflowPanel` コンポーネント。
*   イベントを通じて親コンポーネントと疎に連携する、よりクリーンなアーキテクチャ。
*   `Show.php` からワークフロー関連のコードが一掃され、見通しが大幅に改善された状態。
