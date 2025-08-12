<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class WorkflowActionButtons extends Component
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

    /**
     * 承認者選択モーダルを開く (旧 openApprovalRequestModal)
     */
    public function openApproverSelectModal(): void // <<<--- メソッド名変更
    {
        // 権限チェック (自分が点検者か？) - Service 側でも行う
        if (!$this->canRequestApproval()) {
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.unauthorized'), type: 'error');
            } else {
                $this->error(__('messages.error.unauthorized')); // Or custom message
            }
            Log::error('Approval request failed: ' . Auth::id() . ' is not the inspector of ledger ' . $this->ledgerRecord->id);

            return;
        }

        $this->assigneeModalRoleType = 'approver'; // 念のため設定
        // 初期選択IDを設定 (実績ベース)
        $initialApproverId = $this->getInitialApproverId(); // <<<--- 初期選択ID取得ヘルパー呼び出し

        $this->resetValidation();
        $this->showAssigneeModal = true;

        $this->dispatch('open-assignee-modal',
            ledgerDefineId: $this->ledgerRecord->define->id,
            folderId: $this->ledgerRecord->define->folder_id,
            roleType: 'approver', // <<<--- 'approver' を指定
            ledgerId: $this->ledgerRecord->id,
            initialUserId: $initialApproverId // <<<--- 初期選択ID
        );
    }

    /**
     * モーダルから承認者が選択されたときのイベントリスナー
     */
    #[On('assignee-selected')]
    public function handleAssigneeSelected(int $userId, string $roleType): void
    {
        if ($this->actionTypeForModal == 'approve_and_select_next') {
            $this->handleNextApproverSelected($userId, $roleType); // <<<--- handleNextApproverSelected を呼び出し

            return;
        }

        if ($roleType !== 'approver' || !$this->canRequestApproval()) {
            // このコンポーネントからの承認者選択以外は無視、または権限がない場合は処理しない
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.unauthorized'), type: 'error');
            } else {
                $this->error(__('messages.error.unauthorized'));
            }
            Log::warning("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");

            return;
        }

        $this->selectedApproverId = $userId; // 一時的に保持 (コメントモーダルに渡すため)
        // ★ 担当者選択後、コメント入力モーダルを開く
        $this->openCommentModal('request_approval_with_comment');
    }

    // --- 承認者の初期選択IDを取得するヘルパー ---
    protected function getInitialApproverId(): ?int
    {
        // 実績ベースで取得 (CreateColumn と同じロジック)
        $frequentUsers = $this->workflowService->getFrequentAssignees($this->ledgerRecord->define->id, 'approver', 1);

        return !empty($frequentUsers) ? $frequentUsers[0]['id'] : null;
    }

    /**
     * 作成中に戻すモーダルを開く
     */
    public function openReturnToDraftModal(): void
    {
        // 権限チェック (自分が担当者か？)
        if (!$this->canReturnToDraft()) {
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.unauthorized'), type: 'error');
            } else {
                $this->error(__('messages.error.unauthorized'));
            }

            return;
        }

        //        $this->returnComment = ''; // コメントをリセット
        //        $this->returnToDraftModal = true;
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
        // 推奨ユーザー
        if ($this->ledgerRecord->define->recommendedApprover) {
            $approver = $this->ledgerRecord->define->recommendedApprover;
            $options[$approver->id] = ['id' => $approver->id, 'name' => $approver->name . ' (' . __('ledger.workflow.recommended_user') . ')'];
            $this->selectedApproverId = $approver->id;
        }
        // 推奨ロール
        if ($this->ledgerRecord->define->recommendedApproverRole) {
            // ... (ロールユーザー取得ロジック) ...
        }
        // その他ユーザー
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
        if (!$this->canApprove()) { // ここで canBeFinallyApproved() が呼ばれる
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.unauthorized_or_conditions_not_met'), type: 'error');
            } else {
                $this->error(__('messages.error.unauthorized_or_conditions_not_met')); // 翻訳キー例
            }

            return;
        }
        $this->openCommentModal('approve'); // コメントモーダルを開く
    }

    /**
     * 中間承認で、次の承認者を選択するモーダルを開く
     */
    public function openNextApproverSelectModal(): void
    {
        // $this->actionTypeForModal が 'approve_and_select_next' になっているはず
        // $this->commentForModal にコメントが保持されているはず
        $this->assigneeModalRoleType = 'approver';
        // 初期選択ID (実績ベースなど)
        $initialNextApproverId = $this->getInitialApproverIdExcludingSelfAndCurrent();

        $this->dispatch('open-assignee-modal',
            ledgerDefineId: $this->ledgerRecord->define->id,
            folderId: $this->ledgerRecord->define->folder_id,
            roleType: 'approver',
            ledgerId: $this->ledgerRecord->id,
            initialUserId: $initialNextApproverId,
        // どの親コンポーネントのどのイベントをリッスンするか識別子を追加 (任意)
        // targetEvent: 'next-approver-selected-for-show'
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
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-success', message: __('あなたの承認処理は完了し、次の承認者に依頼されました。'), type: 'success');
            } else {
                $this->success(__('あなたの承認処理は完了し、次の承認者に依頼されました。'));
            }

            // $this->loadWorkflowHistory(); // Show.php で loadWorkflowHistory を呼び出すため、ここでは不要
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知
        } catch (\Exception $e) {
            Log::error('Finalizing approval with next assignee failed: ' . $e->getMessage());
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.generic'), type: 'error');
            } else {
                $this->error(__('messages.error.generic'), $e->getMessage());
            }
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
            // $this->loadWorkflowHistory(); // Show.php で loadWorkflowHistory を呼び出すため、ここでは不要
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-success', message: __('ledger.workflow.returned_to_draft_message'), type: 'success');
            } else {
                $this->success(__('ledger.workflow.returned_to_draft_message'));
            }
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知
        } catch (\Exception $e) {
            Log::error('Return to draft failed: ' . $e->getMessage());
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.generic'), type: 'error');
            } else {
                $this->error(__('messages.error.generic'));
            }
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
                if (app()->runningUnitTests()) {
                    $this->dispatch('test-mary-toast-success', message: __('ledger.workflow.approved_message'), type: 'success');
                }
            } elseif ($this->actionTypeForModal === 'return_to_draft') {
                $this->ledgerRecord = $this->workflowService->returnToDraft($ledgerId, $modifierId, $comment);
                $this->success(__('ledger.workflow.returned_to_draft_message'));
                if (app()->runningUnitTests()) {
                    $this->dispatch('test-mary-toast-success', message: __('ledger.workflow.returned_to_draft_message'), type: 'success');
                }
            }

            // $this->loadWorkflowHistory(); // Show.php で loadWorkflowHistory を呼び出すため、ここでは不要
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知
        } catch (\Exception $e) {
            Log::error("Workflow action '{$this->actionTypeForModal}' failed: ".$e->getMessage());
            $this->error(__('messages.error.generic'), $e->getMessage());
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.generic'), type: 'error');
            }
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
                    if (app()->runningUnitTests()) {
                        $this->dispatch('test-mary-toast-success', message: __('ledger.workflow.approved_message'), type: 'success');
                    } else {
                        $this->success(__('ledger.workflow.approved_message'));
                    }
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
                if (app()->runningUnitTests()) {
                    $this->dispatch('test-mary-toast-success', message: __('ledger.workflow.returned_to_draft_message'), type: 'success');
                } else {
                    $this->success(__('ledger.workflow.returned_to_draft_message'));
                }
            } elseif ($actionType === 'request_approval_with_comment') {
                if (! $this->canRequestApproval() || is_null($this->selectedApproverId)) {
                    throw new \Exception(__('messages.error.unauthorized_or_missing_approver'));
                }
                $this->ledgerRecord = $this->workflowService->requestApproval($ledgerId, $this->selectedApproverId, $modifierId, $comment);
                if (app()->runningUnitTests()) {
                    $this->dispatch('test-mary-toast-success', message: __('ledger.workflow.approval_requested_message'), type: 'success');
                } else {
                    $this->success(__('ledger.workflow.approval_requested_message'));
                }
            }
            $this->dispatch('workflowUpdated'); // 親コンポーネントに通知

        } catch (\Exception $e) {
            Log::error("Workflow action '{$actionType}' failed: ".$e->getMessage());
            if (app()->runningUnitTests()) {
                $this->dispatch('test-mary-toast-error', message: __('messages.error.generic'), type: 'error');
            } else {
                $this->error(__('messages.error.generic'), $e->getMessage());
            }
        } finally {
            $this->selectedApproverId = null;
        }
    }

    public function render()
    {
        return view('livewire.ledger.workflow-action-buttons');
    }
}

