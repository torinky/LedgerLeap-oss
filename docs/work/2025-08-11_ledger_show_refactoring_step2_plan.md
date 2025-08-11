# `app/Livewire/Ledger/Show.php` リファクタリング計画 - Step 2: WorkflowPanel コンポーネントの分離

## 1. 目的

`Show.php` から、ワークフローの状態表示、アクションボタン、関連モーダルといった、ワークフローに特化したUIとそのロジックを、新しいLivewire子コンポーネント `WorkflowPanel` に分離します。これにより、親コンポーネントの責務をさらに軽減し、ワークフロー関連のUIを独立して管理・開発できるようにします。

## 2. 段階的アプローチとテンプレート構築

大規模な変更によるリスクを低減し、今後の開発効率を向上させるため、段階的なアプローチを採用します。

このアプローチは、単にリスクを低減するだけでなく、**今後のリファクタリング作業における標準的な進め方（テンプレート）を構築する**ことを重要な目的としています。

まず**「承認」機能**のみを対象にリファクタリングを先行して実施し、コンポーネントの分離からテスト完了までの一連のプロセスを確立します。この成功モデルを、後続のステップで他のワークフロー機能（差し戻し、申請取り下げ等）に展開します。最終的には、`Show` コンポーネントに含まれる他のUI部品も同様の手法で分離・コンポーネント化していくことを見据えています。

## 3. 詳細な作業計画

### Step 2.1: 「承認」機能の分離とテスト（テンプレート構築）

1.  **`WorkflowPanel` コンポーネントの作成:**
    *   `vendor/bin/sail artisan make:livewire Ledger/WorkflowPanel` コマンドを実行し、`WorkflowPanel.php` と `workflow-panel.blade.php` を生成します。

2.  **プロパティとメソッドの移管（「承認」機能）:**
    *   `Show.php` から、「承認」に関連するプロパティ（例: `approvalComment`, `approvalRequestModal`）とメソッド（`approveTask`, `openApprovalCommentModal`）を `WorkflowPanel.php` に移管します。
    *   `WorkflowPanel.php` は、親から渡される `LedgerRecord` を受け取るための `public Ledger $ledgerRecord;` プロパティを定義します。
    *   必要なサービス（`WorkflowService` など）をコンストラクタまたは `mount` メソッドで依存注入します。

3.  **ビューの分離（「承認」機能）:**
    *   `show.blade.php` から、「承認」ボタンとそれに関連するコメント入力モーダルのコードを `workflow-panel.blade.php` に移管します。
    *   `show.blade.php` の元の場所には、`<livewire:ledger.workflow-panel :ledgerRecord="$ledgerRecord" wire:key="workflow-panel-{{ $ledgerRecord->id }}" />` のようにコンポーネントを埋め込みます。`wire:key` を指定し、親のデータが更新された際にコンポーネントが確実に再レンダリングされるようにします。

4.  **コンポーネント間の連携（イベント駆動）:**
    *   `WorkflowPanel` の `approveTask` メソッドが成功した際に、`$this->dispatch('workflowUpdated');` を実行してイベントを発行します。
    *   親である `Show.php` は、`#[On('workflowUpdated')]` アトリビュートを使ってこのイベントをリッスンし、`$this->ledgerRecord->refresh()` を実行して最新の台帳情報を再読み込みします。

5.  **テストの作成と実行（「承認」機能）:**
    *   **新規作成:** `tests/Feature/Livewire/Ledger/WorkflowPanelTest.php` を作成します。
        *   「承認」権限がある場合に「承認」ボタンが表示されることをテストします。
        *   「承認」ボタンクリックで、承認コメントモーダルが表示されることをテストします。
        *   `approveTask` の実行後に `workflowUpdated` イベントが正しく発行されることをテストします。
    *   **既存テストの修正:** `ShowTest.php` を修正し、`WorkflowPanel` コンポーネントが読み込まれていること、`workflowUpdated` イベント受信後に `Show` コンポーネントの状態が更新されることを検証します。

### Step 2.2以降: 他機能への展開

「承認」機能で確立したリファクタリング手法を、他のワークフロー機能（差し戻し、申請取り下げ等）に順次適用します。

## 4. 期待される成果物

*   「承認」機能がカプセル化された `WorkflowPanel` コンポーネント。
*   イベントを通じて親コンポーネントと疎に連携する、クリーンなアーキテクチャの確立。
*   `Show.php` から「承認」機能に関するコードが削減され、見通しが改善された状態。
*   他の機能にも適用可能な、テストに裏付けられたリファクタリングの**成功モデル（テンプレート）**。
