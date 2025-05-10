<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use Toast, AuthorizesRequests;

    public $canView = false;

    public Ledger $ledgerRecord; // タイプヒントを明確に
    public $ledgerDefineRecord; // define はリレーションでアクセス可能

    public $canUpdate = false;
    // --- Workflow Action 用 ---
    public bool $approvalRequestModal = false;
    public bool $returnToDraftModal = false;
    public ?int $selectedApproverId = null;
    public array $approverOptions = [];
    public string $returnComment = ''; // 詳細画面ではタスクIDは不要
    protected WorkflowService $workflowService;

    // --- モーダル制御用プロパティ ---
    public bool $showAssigneeModal = false; // 承認者選択モーダル用
    public string $assigneeModalRoleType = 'approver'; // 固定で approver

    public Collection $workflowHistory; // ワークフロー履歴用プロパティ

    public $selectedTab = 'details';

    // WorkflowService をインジェクト
    public function boot(WorkflowService $workflowService): void
    {
        $this->workflowService = $workflowService;
    }

    // WorkflowService をインジェクト
    public function mount(int $ledgerId): void // <<<--- Request $request 不要
    {
        // Eager load で必要な情報を取得
        $this->ledgerRecord = Ledger::with([
            'define',
            'modifier:id,name', // <<<--- 取得カラム指定推奨
            'creator:id,name',
            'latestDiff.inspector:id,name', // <<<--- 最新Diffの担当者も取得
            'latestDiff.approver:id,name'
        ])
            ->findOrFail($ledgerId);
        $this->ledgerDefineRecord = $this->ledgerRecord->define;

        $this->loadWorkflowHistory();

        // 権限チェック (閲覧権限) - 必要に応じて実装
        // $this->authorize('view', $this->ledgerRecord);

        // 権限チェックはせず画面内のカラムを伏せる
//        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord->define]);
    }

    protected function loadWorkflowHistory(): void
    {
        $this->workflowHistory = $this->ledgerRecord->ledgerDiff()
            ->with(['modifier:id,name', 'inspector:id,name', 'approver:id,name']) // 必要な情報をEager Load
            ->orderBy('created_at', 'desc') // 新しい順
            ->orderBy('id', 'desc') // 同時刻ならIDで
            ->get();
    }


    /**
     * 点検完了・承認申請モーダルを開く
     */
/*    public function openApprovalRequestModal(): void
    {
        // 権限チェック (自分が点検者か？)
        if (!$this->canRequestApproval()) return;

        $this->selectedApproverId = null;
        $this->loadApproverOptions(); // 承認者候補ロード
        if (empty($this->approverOptions)) {
            $this->error(__('ledger.workflow.no_approvers_found'));
            return;
        }
        $this->approvalRequestModal = true;
    }*/
    /**
     * 承認者選択モーダルを開く (旧 openApprovalRequestModal)
     */
    public function openApproverSelectModal(): void // <<<--- メソッド名変更
    {
        // 権限チェック (自分が点検者か？) - Service 側でも行う
        if (!$this->canRequestApproval()) {
            $this->error(__('messages.error.unauthorized')); // Or custom message
            Log::error('Approval request failed: ' . Auth::id() . ' is not the inspector of ledger ' . $this->ledgerRecord->id);
            return;
        }

        $this->assigneeModalRoleType = 'approver'; // 念のため設定
        // 初期選択IDを設定 (実績ベース)
        $initialApproverId = $this->getInitialApproverId(); // <<<--- 初期選択ID取得ヘルパー呼び出し

        $this->resetValidation();
        $this->showAssigneeModal = true;

        $this->dispatch('open-assignee-modal',
            ledgerDefineId: $this->ledgerDefineRecord->id,
            folderId: $this->ledgerDefineRecord->folder_id,
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
        // このコンポーネントは承認者選択のみを扱う想定
        if ($roleType !== 'approver') {
            Log::warning("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");
            return;
        }

        Log::debug("Approver selected via modal: User ID {$userId}");
        $this->requestApprovalInternal($userId); // 承認申請処理を呼び出す

        // モーダルは子コンポーネント側で閉じるはず
        $this->showAssigneeModal = false;
    }

    /**
     * 承認申請を実行する内部メソッド
     */
    protected function requestApprovalInternal(int $approverId): void
    {
        // 権限チェック (再度確認)
        if (!$this->canRequestApproval()) { $this->error(__('messages.error.unauthorized')); return; }
        // 担当者IDのバリデーション (念のため)
        if(!User::find($approverId)){ $this->error(__('ledger.workflow.invalid_approver')); return; }

        try {
            // Service 呼び出し (引数は Ledger ID, 承認者ID, 点検者ID=自分)
            $this->ledgerRecord = $this->workflowService->requestApproval(
                $this->ledgerRecord->id,
                $approverId, // <<<--- モーダルで選択された ID
                Auth::id()    // <<<--- 操作者は自分 (点検者)
            );
            // $this->approvalRequestModal = false; // handleAssigneeSelected で閉じる
            $this->loadWorkflowHistory(); // 履歴を更新
            $this->success(__('ledger.workflow.approval_requested_message'));
        } catch (\Exception $e) {
            Log::error("Approval request failed: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    // --- 承認者の初期選択IDを取得するヘルパー ---
    protected function getInitialApproverId(): ?int
    {
        // 実績ベースで取得 (CreateColumn と同じロジック)
        $frequentUsers = $this->workflowService->getFrequentAssignees($this->ledgerDefineRecord->id, 'approver', 1);
        return !empty($frequentUsers) ? $frequentUsers[0]['id'] : null;
    }


    /**
     * 作成中に戻すモーダルを開く
     */
    public function openReturnToDraftModal(): void
    {
        // 権限チェック (自分が担当者か？)
        if (!$this->canReturnToDraft()) return;

        $this->returnComment = ''; // コメントをリセット
        $this->returnToDraftModal = true;
    }

    /**
     * 承認者の選択肢をロードする
     */
    public function loadApproverOptions(): void
    {
        if (!$this->ledgerDefineRecord) return;

        $options = [];
        // 推奨ユーザー
        if ($this->ledgerDefineRecord->recommendedApprover) {
            $approver = $this->ledgerDefineRecord->recommendedApprover;
            $options[$approver->id] = ['id' => $approver->id, 'name' => $approver->name . ' (' . __('ledger.workflow.recommended_user') . ')'];
            $this->selectedApproverId = $approver->id;
        }
        // 推奨ロール
        if ($this->ledgerDefineRecord->recommendedApproverRole) {
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


    /**
     * 承認を実行
     */
    public function approveTask(): void
    {
        // 権限チェック
        if (!$this->canApprove()) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }

        try {
            // 修正: Service 呼び出し (引数は Ledger ID)
            $this->ledgerRecord = $this->workflowService->approve(
                $this->ledgerRecord->id,
                Auth::id()
            );
            $this->loadWorkflowHistory();
            $this->success(__('ledger.workflow.approved_message'));
        } catch (\Exception $e) {
            Log::error("Approval failed: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    /**
     * 作成中に戻す
     */
    public function returnTaskToDraft(): void
    {
        // 権限チェック
        if (!$this->canReturnToDraft()) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }

        // コメント必須の場合のバリデーション (任意)
        // $this->validate(['returnComment' => ['required', 'string', 'max:1000']]);

        try {
            // 修正: Service 呼び出し (引数は Ledger ID)
            $this->ledgerRecord = $this->workflowService->returnToDraft(
                $this->ledgerRecord->id,
                Auth::id(),
                $this->returnComment
            );
            $this->returnToDraftModal = false;
            $this->loadWorkflowHistory();
            $this->success(__('ledger.workflow.returned_to_draft_message'));
        } catch (\Exception $e) {
            Log::error("Return to draft failed: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        } finally {
            $this->returnComment = '';
        }
    }

    // --- 権限チェック用ヘルパーメソッド ---
    public function canRequestApproval(): bool
    {
        // 現在のユーザーが点検者であるか、かつステータスが点検待ちか
        return $this->ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION &&
            $this->ledgerRecord->latestDiff?->inspector_id === Auth::id();
    }

    public function canApprove(): bool
    {
        // 現在のユーザーが承認者であるか、かつステータスが承認待ちか
        return $this->ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL &&
            $this->ledgerRecord->latestDiff?->approver_id === Auth::id();
    }

    public function canReturnToDraft(): bool
    {
        // 現在のユーザーが点検者または承認者であるか、かつステータスが承認待ちまたは点検待ちか
        return (
            ($this->ledgerRecord->status === WorkflowStatus::PENDING_INSPECTION && $this->ledgerRecord->latestDiff?->inspector_id === Auth::id()) ||
            ($this->ledgerRecord->status === WorkflowStatus::PENDING_APPROVAL && $this->ledgerRecord->latestDiff?->approver_id === Auth::id())
        );
    }

    public function render()
    {
        return view('livewire.ledger.show')
            ->layout('layouts.app'); // レイアウト指定
    }
}
