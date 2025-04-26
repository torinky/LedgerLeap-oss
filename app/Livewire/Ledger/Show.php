<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use Toast, AuthorizesRequests;

    public $canView = false;

    public Ledger $ledgerRecord; // タイプヒントを明確に
    public $ledgerDefineRecord; // define はリレーションでアクセス可能

    // --- Workflow Action 用 ---
    public bool $approvalRequestModal = false;
    public bool $returnToDraftModal = false;
    public ?int $selectedApproverId = null;
    public array $approverOptions = [];
    public string $returnComment = ''; // 詳細画面ではタスクIDは不要
    protected WorkflowService $workflowService;
    // --- ここまで ---

    public Collection $workflowHistory; // ワークフロー履歴用プロパティ

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
    public function openApprovalRequestModal(): void
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
     * 点検完了・承認申請を実行
     */
    public function requestApproval(): void
    {
        // 権限チェック
        if (!$this->canRequestApproval()) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }

        $validated = $this->validate([
            'selectedApproverId' => ['required', 'integer', 'exists:users,id']
        ]);

        try {
            // 修正: Service 呼び出し (引数は Ledger ID)
            $this->ledgerRecord = $this->workflowService->requestApproval(
                $this->ledgerRecord->id,
                $validated['selectedApproverId'],
                Auth::id()
            );
            $this->approvalRequestModal = false;
            $this->success(__('ledger.workflow.approval_requested_message'));
            // 必要なら $refresh など
        } catch (\Exception $e) {
            Log::error("Approval request failed: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        } finally {
            $this->selectedApproverId = null;
        }
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
            $this->success(__('ledger.workflow.returned_to_draft_message'));
        } catch (\Exception $e) {
            Log::error("Return to draft failed: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        } finally {
            $this->returnComment = '';
        }
    }

    // --- 権限チェック用ヘルパーメソッド (例) ---
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
