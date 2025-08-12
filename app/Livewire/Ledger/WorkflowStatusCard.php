<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\WorkflowService;
use Arr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class WorkflowStatusCard extends Component
{
    use AuthorizesRequests, Toast;

    public Ledger $ledgerRecord;

    // --- Workflow Action 用 ---
    public bool $approvalRequestModal = false;
    public bool $returnToDraftModal = false;
    public ?int $selectedApproverId = null;
    public bool $showAssigneeModalForNext = false;
    public string $nextAssigneeRoleType = 'approver';
    public array $approverOptions = [];
    public string $returnComment = '';
    public bool $showCommentModal = false;
    public string $actionTypeForModal = '';
    public string $commentForModal = '';
    public bool $showAssigneeModal = false;
    protected string $assigneeModalRoleType = 'approver';
    protected WorkflowService $workflowService;

    public function boot(WorkflowService $workflowService): void
    {
        $this->workflowService = $workflowService;
    }

    // #[Computed] プロパティとしてワークフロー履歴を定義
    #[Computed]
    public function workflowHistory(): Collection
    {
        return $this->ledgerRecord->ledgerDiff()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->get();
    }

    // #[Computed] プロパティとして必須ロール進捗を定義
    #[Computed]
    public function requiredRolesProgress(): array
    {
        if ($this->ledgerRecord->define->workflow_enabled && $this->ledgerRecord->define?->folder) {
            return $this->ledgerRecord->getRequiredRolesProgressDetails();
        }
        return [];
    }

    /**
     * 承認者選択モーダルを開く (旧 openApprovalRequestModal)
     */
    public function openApproverSelectModal(): void
    {
        Log::debug('WorkflowStatusCard: openApproverSelectModal called.');
        if (!$this->canRequestApproval()) {
            $this->error(__('messages.error.unauthorized'));
            Log::error('Approval request failed: ' . Auth::id() . ' is not the inspector of ledger ' . $this->ledgerRecord->id);

            return;
        }

        $this->assigneeModalRoleType = 'approver';
        $initialApproverId = $this->getInitialApproverId();

        $this->resetValidation();
        $this->showAssigneeModal = true;

        $this->dispatch('open-assignee-modal',
            ledgerDefineId: $this->ledgerRecord->define->id,
            folderId: $this->ledgerRecord->define->folder_id,
            roleType: 'approver',
            ledgerId: $this->ledgerRecord->id,
            initialUserId: $initialApproverId
        );
    }

    /**
     * モーダルから承認者が選択されたときのイベントリスナー
     */
    #[On('assignee-selected')]
    public function handleAssigneeSelected(int $userId, string $roleType): void
    {
        if ($this->actionTypeForModal == 'approve_and_select_next') {
            $this->handleNextApproverSelected($userId, $roleType);

            return;
        }
        if ($roleType !== 'approver' || !$this->canRequestApproval()) {
            $this->error(__('messages.error.unauthorized'));
            Log::warning("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");

            return;
        }
        $this->selectedApproverId = $userId;
        $this->openCommentModal('request_approval_with_comment');
    }

    // --- 承認者の初期選択IDを取得するヘルパー ---
    protected function getInitialApproverId(): ?int
    {
        $frequentUsers = $this->workflowService->getFrequentAssignees($this->ledgerRecord->define->id, 'approver', 1);

        return !empty($frequentUsers) ? $frequentUsers[0]['id'] : null;
    }

    /**
     * 作成中に戻すモーダルを開く
     */
    public function openReturnToDraftModal(): void
    {
        Log::debug('WorkflowStatusCard: openReturnToDraftModal called.');
        if (!$this->canReturnToDraft()) {
            $this->error(__('messages.error.unauthorized'));

            return;
        }
        $this->openCommentModal('return_to_draft');
    }

    /**
     * 承認者の選択肢をロードする
     */
    public function loadApproverOptions(): void
    {
        if (!$this->ledgerRecord->define) {
            return;
        }

        $options = [];
        if ($this->ledgerRecord->define->recommendedApprover) {
            $approver = $this->ledgerRecord->define->recommendedApprover;
            $options[$approver->id] = ['id' => $approver->id, 'name' => $approver->name . ' (' . __('ledger.workflow.recommended_user') . ')'];
            $this->selectedApproverId = $approver->id;
        }
        if ($this->ledgerRecord->define->recommendedApproverRole) {
            // ... (ロールユーザー取得ロジック) ...
        }
        $allUsers = User::orderBy('name')->get();
        foreach ($allUsers as $user) {
            if (!isset($options[$user->id])) {
                $options[$user->id] = ['id' => $user->id, 'name' => $user->name];
            }
        }
        $this->approverOptions = array_values($options);
    }

    public function approveTask(): void
    {
        Log::debug('WorkflowStatusCard: approveTask called.');
        if (!$this->canApprove()) {
            $this->error(__('messages.error.unauthorized_or_conditions_not_met'));

            return;
        }
        $this->openCommentModal('approve');
    }

    /**
     * 中間承認で、次の承認者を選択するモーダルを開く
     */
    public function openNextApproverSelectModal(): void
    {
        $this->assigneeModalRoleType = 'approver';
        $initialNextApproverId = $this->getInitialApproverIdExcludingSelfAndCurrent();

        $this->dispatch('open-assignee-modal',
            ledgerDefineId: $this->ledgerRecord->define->id,
            folderId: $this->ledgerRecord->define->folder_id,
            roleType: 'approver',
            ledgerId: $this->ledgerRecord->id,
            initialUserId: $initialNextApproverId,
        );
    }

    /**
     * 次の承認者選択モーダルから担当者が選択されたときのイベントリスナー
     */
    public function handleNextApproverSelected(int $userId, string $roleType): void
    {
        if ($this->actionTypeForModal !== 'approve_and_select_next' || $roleType !== 'approver') {
            return;
        }
        Log::debug("Next Approver selected: {$userId} for Ledger ID {$this->ledgerRecord->id}");

        try {
            $this->ledgerRecord = $this->workflowService->approve(
                $this->ledgerRecord->id,
                Auth::id(),
                $this->commentForModal,
                $userId
            );
            $this->success(__('あなたの承認処理は完了し、次の承認者に依頼されました。'));

            $this->loadWorkflowHistory();
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知
        } catch (\Exception $e) {
            Log::error('Finalizing approval with next assignee failed: ' . $e->getMessage());
            $this->error(__('messages.error.generic'), $e->getMessage());
        } finally {
            $this->commentForModal = '';
            $this->actionTypeForModal = '';
            $this->selectedApproverId = null;
        }
    }

    // --- 次の承認者の初期選択IDを取得 (自分と現在の承認担当者を除く) ---
    protected function getInitialApproverIdExcludingSelfAndCurrent(): ?int
    {
        $excludeIds = [$this->ledgerRecord->latestDiff?->approver_id, Auth::id()];
        $frequentUsers = $this->workflowService->getFrequentAssignees($this->ledgerRecord->define->id, 'approver', 5, '', $excludeIds);

        return !empty($frequentUsers) ? $frequentUsers[0]['id'] : null;
    }

    /**
     * 作成中に戻す
     */
    public function returnTaskToDraft(): void
    {
        if (!$this->canReturnToDraft()) {
            $this->error(__('messages.error.unauthorized'));

            return;
        }

        try {
            $this->ledgerRecord = $this->workflowService->returnToDraft(
                $this->ledgerRecord->id,
                Auth::id(),
                $this->returnComment
            );
            $this->returnToDraftModal = false;
            $this->loadWorkflowHistory();
            $this->success(__('ledger.workflow.returned_to_draft_message'));
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知
        } catch (\Exception $e) {
            Log::error('Return to draft failed: ' . $e->getMessage());
            $this->error(__('messages.error.generic'));
        } finally {
            $this->returnComment = '';
        }
    }

    // --- 権限チェック用ヘルパーメソッド ---
    public function canRequestApproval(): bool
    {
        return $this->workflowService->canRequestApproval(Auth::user(), $this->ledgerRecord);
    }

    public function canApprove(): bool
    {
        return $this->workflowService->canApprove(Auth::user(), $this->ledgerRecord);
    }

    public function canReturnToDraft(): bool
    {
        return $this->workflowService->canReturnToDraft(Auth::user(), $this->ledgerRecord);
    }

    public function openCommentModal(string $actionType): void
    {
        $title = '';
        $actionLabel = '';
        $actionClass = '';
        $text = '';

        switch ($actionType) {
            case 'approve':
                $title = __('ledger.workflow.approve').' - '.__('ledger.workflow.comments');
                $actionLabel = __('ledger.workflow.approve');
                $actionClass = 'btn-primary';
                break;
            case 'return_to_draft':
                $title = __('ledger.workflow.return_to_draft').' - '.__('ledger.workflow.comments');
                $actionLabel = __('ledger.workflow.return_to_draft');
                $actionClass = 'btn-warning';
                break;
            case 'request_approval_with_comment':
                if (is_null($this->selectedApproverId)) {
                    $this->warning(__('ledger.workflow.select_approver_first'));
                    $this->openApproverSelectModal();

                    return;
                }
                $title = __('ledger.workflow.request_approval_short').' - '.__('ledger.workflow.comments');
                $actionLabel = __('ledger.workflow.request_approval');
                $actionClass = 'btn-success';
                break;
        }

        if ($title) {
            $this->dispatch('open-workflow-comment-modal',
                title: $title,
                actionLabel: $actionLabel,
                actionClass: $actionClass,
                actionType: $actionType,
                ledgerId: $this->ledgerRecord->id,
                initialComment: '',
                text: $text
            );
        }
    }

    // --- コメント付きでアクション実行 ---
    public function executeActionWithComment(): void
    {
        $this->validate(['commentForModal' => 'nullable|string|max:1000']);

        $ledgerId = $this->ledgerRecord->id;
        $modifierId = Auth::id();
        $comment = $this->commentForModal;

        try {
            if ($this->actionTypeForModal === 'approve') {
                $this->ledgerRecord = $this->workflowService->approve($ledgerId, $modifierId, $comment);
                $this->success(__('ledger.workflow.approved_message'));
            } elseif ($this->actionTypeForModal === 'return_to_draft') {
                $this->ledgerRecord = $this->workflowService->returnToDraft($ledgerId, $modifierId, $comment);
                $this->success(__('ledger.workflow.returned_to_draft_message'));
            }

            $this->loadWorkflowHistory();
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知
        } catch (\Exception $e) {
            Log::error("Workflow action '{$this->actionTypeForModal}' failed: ".$e->getMessage());
            $this->error(__('messages.error.generic'), $e->getMessage());
        } finally {
            $this->showCommentModal = false;
            $this->commentForModal = '';
            $this->actionTypeForModal = '';
        }
    }

    public function getCommentModalTitle(): string
    {
        return match ($this->actionTypeForModal) {
            'approve' => __('ledger.workflow.approve').' - '.__('ledger.workflow.comments'),
            'return_to_draft' => __('ledger.workflow.return_to_draft').' - '.__('ledger.workflow.comments'),
            default => __('ledger.workflow.comments'),
        };
    }

    public function getCommentModalActionLabel(): string
    {
        return match ($this->actionTypeForModal) {
            'approve' => __('ledger.workflow.approve'),
            'return_to_draft' => __('ledger.workflow.return_to_draft'),
            default => __('Execute'),
        };
    }

    public function getCommentModalActionClass(): string
    {
        return match ($this->actionTypeForModal) {
            'approve' => 'btn-primary',
            'return_to_draft' => 'btn-warning',
            default => 'btn-secondary',
        };
    }

    #[On('workflow-action-with-comment')]
    public function handleActionWithComment(string $actionType, int $ledgerId, ?string $comment): void
    {
        if ($ledgerId !== $this->ledgerRecord->id) {
            return;
        }

        $this->commentForModal = $comment ?? '';
        $modifierId = Auth::id();

        try {
            if ($actionType === 'approve') {
                if (! $this->canApprove()) {
                    throw new \Exception(__('messages.error.unauthorized'));
                }

                $progress = $this->ledgerRecord->getRequiredRolesProgressDetails();
                $tempCompletedApproverRoles = $progress['approval']['completed_roles']->pluck('id')->toArray();
                $modifierRoles = User::find($modifierId)?->roles()->pluck('id')->toArray() ?? [];
                foreach ($this->ledgerRecord->define->folder->requiredApproverRoles as $reqRole) {
                    if (in_array($reqRole->id, $modifierRoles) && ! in_array($reqRole->id, $tempCompletedApproverRoles)) {
                        $tempCompletedApproverRoles[] = $reqRole->id;
                    }
                }

                $allInspectionsDone = $progress['inspection']['is_all_completed'];
                $allApprovalsWillBeDone = collect($this->ledgerRecord->define->folder->requiredApproverRoles)
                    ->every(fn ($role) => in_array($role->id, $tempCompletedApproverRoles));

                if ($allInspectionsDone && $allApprovalsWillBeDone) {
                    $this->ledgerRecord = $this->workflowService->approve($ledgerId, $modifierId, $comment, null);
                    $this->success(__('ledger.workflow.approved_message'));
                } else {
                    $this->commentForModal = $comment;
                    $this->actionTypeForModal = 'approve_and_select_next';
                    $this->openNextApproverSelectModal();

                    return;
                }
            } elseif ($actionType === 'return_to_draft') {
                if (! $this->canReturnToDraft()) {
                    throw new \Exception(__('messages.error.unauthorized'));
                }
                $this->ledgerRecord = $this->workflowService->returnToDraft($ledgerId, $modifierId, $comment);
                $this->success(__('ledger.workflow.returned_to_draft_message'));
            } elseif ($actionType === 'request_approval_with_comment') {
                if (! $this->canRequestApproval() || is_null($this->selectedApproverId)) {
                    throw new \Exception(__('messages.error.unauthorized_or_missing_approver'));
                }
                $this->ledgerRecord = $this->workflowService->requestApproval($ledgerId, $this->selectedApproverId, $modifierId, $comment);
                $this->success(__('ledger.workflow.approval_requested_message'));
            }
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知

        } catch (\Exception $e) {
            Log::error("Workflow action '{$actionType}' failed: ".$e->getMessage());
            $this->error(__('messages.error.generic'), $e->getMessage());

        } finally {
            $this->selectedApproverId = null;
        }
    }

    public function render()
    {
        Log::debug('WorkflowStatusCard render() - Start');
        Log::debug('WorkflowStatusCard render() - ledgerRecord ID: ' . ($this->ledgerRecord->id ?? 'null'));
        Log::debug('WorkflowStatusCard render() - ledgerRecord->define exists: ' . ($this->ledgerRecord->define ? 'true' : 'false'));
        Log::debug('WorkflowStatusCard render() - ledgerRecord->latestDiff exists: ' . ($this->ledgerRecord->latestDiff ? 'true' : 'false'));
        Log::debug('WorkflowStatusCard render() - ledgerRecord->latestDiff->inspector exists: ' . ($this->ledgerRecord->latestDiff?->inspector ? 'true' : 'false'));
        Log::debug('WorkflowStatusCard render() - ledgerRecord->define->folder exists: ' . ($this->ledgerRecord->define?->folder ? 'true' : 'false'));
        Log::debug('WorkflowStatusCard render() - End');
        return view('livewire.ledger.workflow-status-card');
    }
}
