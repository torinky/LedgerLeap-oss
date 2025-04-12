<?php

namespace App\Livewire\Workflow;

use App\Repositories\WorkflowTaskRepository;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PendingList extends Component
{
    use WithPagination;

public $selectedTaskId = null;
    public ?int $selectedApproverId = null; // アクション対象のタスクID
        protected WorkflowTaskRepository $taskRepository; // 承認申請時の選択用

    public function boot(WorkflowTaskRepository $repository): void
    {
        $this->taskRepository = $repository;
    }

    public function render()
    {
        $pendingTasks = $this->taskRepository->getPendingTasksForUser(Auth::user());
        return view('livewire.workflow.pending-list', [
            'pendingTasks' => $pendingTasks,
        ])
            ->layout('layouts.app', ['title' => __('ledger.my_portal_title')]); // アプリケーションのレイアウトを使用

    }

    // --- アクションメソッド (ActionButtons から呼び出される) ---
    public function openApprovalRequestModal(int $taskId)
    {
        $this->selectedTaskId = $taskId;
        // 承認者選択モーダル表示イベント等
        $this->dispatch('open-modal', 'approval-request-modal');
        $this->loadApproverOptions(); // 承認者選択肢をロード
    }

    public function loadApproverOptions()
    {
        // 必要に応じて $this->getApproverOptions() の結果をプロパティにセット
    }

    public function requestApproval()
    {
        // バリデーション
        $this->validate(['selectedApproverId' => ['required', 'integer', 'exists:users,id']]);
        // WorkflowService::requestApproval を呼び出す
        // ...
        $this->dispatch('close-modal', 'approval-request-modal');
        $this->dispatch('notify', message: __('ledger.workflow.approval_requested_message'), type: 'success');
    }

    public function approveTask(int $taskId)
    {
        // WorkflowService::approve を呼び出す
        // ...
        $this->dispatch('notify', message: __('ledger.workflow.approved_message'), type: 'success');
    }

    public function returnTaskToDraft(int $taskId, string $comments)
    { // コメントを受け取る
        // WorkflowService::returnToDraft を呼び出す
        // ...
        $this->dispatch('notify', message: __('ledger.workflow.returned_to_draft_message'), type: 'success');
    }

    // 承認者選択肢をロードするメソッド (モーダル表示時に呼ぶ)

    public function getApproverOptions(): array
    {
        // CreateColumn の getInspectorOptions を参考に実装
        // ...
        return [];
    }
}
