<?php

namespace App\Livewire\Workflow;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Repositories\WorkflowTaskRepository;
use App\Services\WorkflowService;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class PendingList extends Component
{
    use Toast, WithPagination;

    #[Locked] // selectedTaskId はURL等から不正に変更されたくない場合 Locked を付ける
    public $selectedTaskId = null;

    public ?int $selectedApproverId = null; // アクション対象のタスクID

    public array $approverOptions = []; // 承認者選択肢

    protected WorkflowTaskRepository $taskRepository; // 承認申請時の選択用

    // --- モーダル制御用プロパティ ---
    public bool $approvalRequestModal = false; // 承認申請モーダルの表示状態
    public bool $returnToDraftModal = false; // 戻し理由モーダルの表示状態
    // ------------------------------


    public array $returnComments = []; // 戻し理由コメント (タスクIDをキーにする)
    /**
     * @var mixed|null
     */
    private mixed $workflowService;

    public function boot(WorkflowTaskRepository $repository, WorkflowService $workflowService): void
    {
        $this->taskRepository = $repository;
        $this->workflowService = $workflowService;
    }

    public function render()
    {
        $pendingTasks = $this->taskRepository->getPendingTasksForUser(Auth::user());

        return view('livewire.workflow.pending-list', [
            'pendingTasks' => $pendingTasks,
        ]);
//            ->layout('layouts.app', ['title' => __('ledger.workflow.title')]); // アプリケーションのレイアウトを使用

    }


    // 承認者選択肢をロードするメソッド (モーダル表示時に呼ぶ)

    public function getApproverOptions(): array
    {
        // CreateColumn の getInspectorOptions を参考に実装
        // ...
        return [];
    }

    /**
     * 承認者選択モーダルを開く
     */
    public function openApprovalRequestModal(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->selectedApproverId = null; // 選択肢をリセット
        $this->loadApproverOptions($taskId); // 承認者候補をロード
        if (empty($this->approverOptions)) {
            $this->error(__('ledger.workflow.no_approvers_found'));
            return;
        }
        $this->approvalRequestModal = true; // <<<--- モーダル表示プロパティを true に
    }


    /**
     * 承認者の選択肢をロードする
     */
    public function loadApproverOptions(int $taskId): void
    {
        $ledgerDiff = LedgerDiff::with('ledger.define')->find($this->selectedTaskId);
        if (!$ledgerDiff || !$ledgerDiff->ledger) {
            $this->approverOptions = [];
            return;
        }
        $ledgerDefine = $ledgerDiff->ledger->define;

        $options = [];
        // 推奨ユーザー
        if ($ledgerDefine?->recommendedApprover) {
            $approver = $ledgerDefine->recommendedApprover;
            $options[$approver->id] = ['id' => $approver->id, 'name' => $approver->name . ' (' . __('ledger.workflow.recommended_user') . ')'];
            $this->selectedApproverId = $approver->id; // デフォルト選択
        }
        // 推奨ロール
        if ($ledgerDefine?->recommendedApproverRole) {
            $roleUsers = $ledgerDefine->recommendedApproverRole->users()->orderBy('name')->get();
            foreach ($roleUsers as $user) {
                if (!isset($options[$user->id])) { // 重複チェック
                    $options[$user->id] = ['id' => $user->id, 'name' => $user->name . ' (' . __('ledger.workflow.recommended_role') . ')'];
                    if (!$this->selectedApproverId) $this->selectedApproverId = $user->id; // ロールが最初ならデフォルト選択
                }
            }
        }
        // その他の全ユーザー (必要に応じてフィルタリング)
        // 例: $allUsers = User::where('is_active', true)->orderBy('name')->get();
        $allUsers = User::orderBy('name')->get();
        foreach ($allUsers as $user) {
            if (!isset($options[$user->id])) { // 重複チェック
                $options[$user->id] = ['id' => $user->id, 'name' => $user->name];
            }
        }

        $this->approverOptions = array_values($options);
    }

    /**
     * 承認申請を実行する
     */
    public function requestApproval(): void
    {
        $validated = $this->validate([
            'selectedApproverId' => ['required', 'integer', 'exists:users,id']
        ]);
        $ledgerDiff = LedgerDiff::find($this->selectedTaskId);

        if (!$ledgerDiff) {
            $this->error('Task not found.'); // エラー処理
            return;
        }

        try {
            $this->workflowService->requestApproval(
                $ledgerDiff->ledger_id,
                $validated['selectedApproverId'],
                Auth::id() // 点検者ID
            );
            $this->dispatch('close-modal', 'approval-request-modal');
            $this->success(__('ledger.workflow.approval_requested_message'));
        } catch (Exception $e) {
            Log::error("Approval request failed: " . $e->getMessage());
            $this->error(__('messages.error.generic')); // 汎用エラーメッセージ
        } finally {
            $this->selectedTaskId = null; // 選択解除
            $this->selectedApproverId = null;
            $this->approvalRequestModal = false;
        }
    }

    /**
     * 承認アクションを実行する
     */
    public function approveTask(int $taskId): void
    {
        $ledgerDiff = LedgerDiff::find($taskId);

        if (!$ledgerDiff) {
            $this->error('Task not found.');
            return;
        }

        // 承認者自身であるかの簡易チェック (より厳密な権限チェックは Service 側でも推奨)
        if ($ledgerDiff->approver_id !== Auth::id()) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }

        try {
            // WorkflowService の approve メソッドを呼び出す
            $this->workflowService->approve($ledgerDiff->ledger_id, Auth::id());

            $this->success(__('ledger.workflow.approved_message'));
//            $this->dispatch('$refresh'); // リストを更新
        } catch (\Exception $e) {
            Log::error("Approval failed for task ID {$taskId}: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    /**
     * 戻し理由入力モーダルを開く
     */
    public function openReturnToDraftModal(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
        // 配列キーが存在しない場合のエラーを防ぐため、初期化
        if (!isset($this->returnComments[$taskId])) {
            $this->returnComments[$taskId] = '';
        }
        $this->returnToDraftModal = true;
    }

    /**
     * タスクを作成中に戻す
     */
    public function returnTaskToDraft(): void
    {
        if ($this->selectedTaskId === null) {
            $this->error('No task selected.'); // エラー処理
            return;
        }

        // コメントを取得 (null の可能性もある)
        $comments = $this->returnComments[$this->selectedTaskId] ?? null;

        // コメント必須の場合のバリデーション (任意)
        // $validated = $this->validate(['returnComments.'.$this->selectedTaskId => ['required', 'string', 'max:1000']]);
        // $comments = $validated['returnComments'][$this->selectedTaskId];

        $ledgerDiff = LedgerDiff::find($this->selectedTaskId);
        if (!$ledgerDiff) {
            $this->error('Task not found.');
            return;
        }

        // 権限チェック (簡易) - Service 側でもチェック推奨
        $canReturn = ($ledgerDiff->status === WorkflowStatus::PENDING_INSPECTION && $ledgerDiff->inspector_id === Auth::id()) ||
            ($ledgerDiff->status === WorkflowStatus::PENDING_APPROVAL && $ledgerDiff->approver_id === Auth::id());

        if (!$canReturn) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }

        try {
            // WorkflowService の returnToDraft メソッドを呼び出す
            $this->workflowService->returnToDraft($ledgerDiff->ledger_id, Auth::id(), $comments);

            $this->returnToDraftModal = false; // モーダルを閉じる
            $this->success(__('ledger.workflow.returned_to_draft_message'));
//            $this->dispatch('$refresh'); // リストを更新
        } catch (\Exception $e) {
            Log::error("Return to draft failed for task ID {$this->selectedTaskId}: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        } finally {
            // コメントと選択タスクIDをリセット
            unset($this->returnComments[$this->selectedTaskId]);
            $this->selectedTaskId = null;
            $this->returnToDraftModal = false;

        }
    }
}
