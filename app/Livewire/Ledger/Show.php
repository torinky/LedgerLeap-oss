<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\WorkflowService;
use Arr;
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

    public bool $showCommentModal = false; // <<<--- コメントモーダル表示用
    public string $actionTypeForModal = ''; // <<<--- モーダルに渡すアクションタイプ
    public string $commentForModal = '';    // <<<--- モーダルとバインドするコメント

    // --- 差分表示用 ---
    public ?LedgerDiff $comparisonTargetDiff = null; // 比較対象の古いDiff
    public array $contentChanges = []; // カラムごとの変更内容
    protected WorkflowService $workflowService;

    // --- モーダル制御用プロパティ ---
    public bool $showAssigneeModal = false; // 承認者選択モーダル用
    public string $assigneeModalRoleType = 'approver'; // 固定で approver

    public Collection $workflowHistory; // ワークフロー履歴用プロパティ

    public $selectedTab = 'details';

    // WorkflowService をインジェクト
    public bool $hasChangedColumns=false;

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
        $this->prepareContentDiff(); // <<<--- 差分データ準備を呼び出し
        // 権限チェック (閲覧権限) - 必要に応じて実装
        // $this->authorize('view', $this->ledgerRecord);

        // 権限チェックはせず画面内のカラムを伏せる
        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord]);
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
/*    public function handleAssigneeSelected(int $userId, string $roleType): void
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
    }*/
    #[On('assignee-selected')]
    public function handleAssigneeSelected(int $userId, string $roleType): void
    {
        if ($roleType !== 'approver' || !$this->canRequestApproval()) {
            // このコンポーネントからの承認者選択以外は無視、または権限がない場合は処理しない
            $this->error(__('messages.error.unauthorized'));
            Log::warning("Assignee selected via modal: User ID {$userId}, Role Type: {$roleType}");
            return;
        }
        $this->selectedApproverId = $userId; // 一時的に保持 (コメントモーダルに渡すため)
        // ★ 担当者選択後、コメント入力モーダルを開く
        $this->openCommentModal('request_approval_with_comment');
    }

    /**
     * 承認申請を実行する内部メソッド
     */
    protected function requestApprovalInternal(int $approverId): void
    {
        // 権限チェック (再度確認)
        if (!$this->canRequestApproval()) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }
        // 担当者IDのバリデーション (念のため)
        if (!User::find($approverId)) {
            $this->error(__('ledger.workflow.invalid_approver'));
            return;
        }

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
        if (!$this->canReturnToDraft()) {
            $this->error(__('messages.error.unauthorized'));
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

    /**
     * 差分表示のためのデータを準備する
     */
    protected function prepareContentDiff(): void
    {
        $this->comparisonTargetDiff = $this->findComparisonTargetDiff();
        $this->contentChanges = [];
//        dd($this->comparisonTargetDiff);

        $currentContentArray = $this->ledgerRecord->content ?? [];
        $currentAttachmentsArray = $this->ledgerRecord->content_attached ?? [];
        $currentColumnDefines = collect($this->ledgerDefineRecord->column_define)->keyBy('id')->all(); // これは stdClass の配列

        $oldColumnDefines = [];
        $oldContentArray = [];
        $oldAttachmentsArray = [];

        if ($this->comparisonTargetDiff && isset($this->comparisonTargetDiff->column_define)) {
            // LedgerDiff->column_define も AsColumnDefinesArrayJson キャストされていれば stdClass の配列
            $oldColumnDefines = collect($this->comparisonTargetDiff->column_define)->keyBy('id')->all();
            $oldContentArray = $this->comparisonTargetDiff->content ?? [];
            $oldAttachmentsArray = $this->comparisonTargetDiff->content_attached ?? [];
        }

        $allColumnIds = array_unique(array_merge(array_keys($currentColumnDefines), array_keys($oldColumnDefines)));
        sort($allColumnIds);

        $this->hasChangedColumns = false;

        foreach ($allColumnIds as $columnId) {
            $currentColumnDefineData = $currentColumnDefines[$columnId] ?? null;
            $oldColumnDefineData = $oldColumnDefines[$columnId] ?? null;

            // --- カラム定義データをそのまま配列/stdClassとして渡す ---
            $displayColumnDefineForCurrent = $currentColumnDefineData ? (array)$currentColumnDefineData : null;
            $displayColumnDefineForOld = $oldColumnDefineData ? (array)$oldColumnDefineData : null;
            // -------------------------------------------------

            $currentValue = Arr::get($currentContentArray, $columnId);
            $currentAttachments = Arr::get($currentAttachmentsArray, $columnId);
            $oldValue = $this->comparisonTargetDiff ? Arr::get($oldContentArray, $columnId) : null;
            $oldAttachments = $this->comparisonTargetDiff ? Arr::get($oldAttachmentsArray, $columnId) : null;

            // ... (isChanged の計算は変更なし) ...
            $normalizedCurrent = is_array($currentValue) || is_object($currentValue) ? json_encode($currentValue) : strval($currentValue);
            $normalizedOld = $this->comparisonTargetDiff && (is_array($oldValue) || is_object($oldValue)) ? json_encode($oldValue) : strval($oldValue);
            $isChanged = $this->comparisonTargetDiff && ($normalizedCurrent !== $normalizedOld);
            if (!$this->comparisonTargetDiff) $isChanged = false;
            if ($isChanged) $this->hasChangedColumns = true;




            $this->contentChanges[$columnId] = [
                'column_define_current' => $displayColumnDefineForCurrent, // 配列/stdClass
                'current_value' => $currentValue,
                'current_attachments' => $currentAttachments,
                'column_define_old' => $displayColumnDefineForOld, // 配列/stdClass
                'old_value' => $oldValue,
                'old_attachments' => $oldAttachments,
                'changed' => $isChanged,
                'column_name' => $currentColumnDefineData->name ?? ($oldColumnDefineData->name ?? __('ledger.column_deleted', ['id' => $columnId])),
            ];
        }
    }

    /**
     * 比較対象となる過去のLedgerDiffを特定するロジック
     * 例: このワークフローの「実質的な開始点」のDiff (最後にDRAFTでなく、内容が記録されたもの)
     */
    protected function findComparisonTargetDiff(): ?LedgerDiff
    {
        // ワークフローが無効、またはDRAFT/NONE状態なら比較対象なし
        if (!$this->ledgerRecord->define->workflow_enabled
//            ||
//            in_array($this->ledgerRecord->status, [WorkflowStatus::DRAFT, WorkflowStatus::NONE])
        ) {
            return null;
        }

        // 比較対象の候補:
        // 1. 最新のDiff (latestDiff) が内容変更を伴うもので、かつ現在のステータスと異なる場合、その一つ前。
        // 2. ワークフロー履歴を遡り、現在のステータスが開始される直前の内容を持つDiff。
        // 3. または、このLedgerのバージョンが1より大きい場合、バージョン-1の最新のDiff。

        // まずはシンプルなロジック: 最新のDiffの一つ前で content があるものを探す
        // (ただし、最新Diffがステータス変更のみの場合、その前のcontentを持つDiffが比較対象になる)
        $latestDiffId = $this->ledgerRecord->latest_diff_id;
        if (!$latestDiffId) {
            // 最新Diffがない場合 (DRAFTから直接PENDINGになったばかりなど) は、バージョン1のDiffなど
            return $this->ledgerRecord->ledgerDiff()
//                ->where('version', 1) // または content is not null
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->orderBy('id', 'asc')
                ->first();
        }
//dd($latestDiffId);
        // 最新Diffがステータス変更のみ (content が空) の場合
        $latestDiffRecord = $this->ledgerRecord->latestDiff;
        if ($latestDiffRecord && (empty($latestDiffRecord->content) || $latestDiffRecord->content == '[]' || $latestDiffRecord->content == '{}')) {
            return LedgerDiff::where('ledger_id', $this->ledgerRecord->id)
                ->where('id', '<', $latestDiffId)
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->latest('id')
                ->first();
        }else{
//            return $latestDiffRecord;
        }
        // 最新Diffが内容変更を含む場合、その一つ前のcontentを持つDiff
        return LedgerDiff::where('ledger_id', $this->ledgerRecord->id)
            ->where('id', '<', $latestDiffId)
            ->whereNotNull('content')
            ->where('content', '<>', '[]')
            ->latest('id')
            ->first();

        // より複雑なロジックの例 (ワークフローの「開始点」を特定)
        // $historyAsc = $this->workflowHistory()->orderBy('created_at', 'asc')->get(); // mountで取得済み
        // $startOfCurrentFlowDiff = null;
        // foreach ($historyAsc->reverse() as $diff) { // 新しい方から遡る
        //     if ($diff->status === WorkflowStatus::DRAFT && $diff->id < $latestDiffId) {
        //         // DRAFT に戻った記録があれば、それより後の最初のPENDINGが今のフローの起点
        //         $startOfCurrentFlowDiff = $historyAsc->where('id', '>', $diff->id)
        //                                            ->whereIn('status', [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])
        //                                            ->sortBy('id')->first();
        //         break;
        //     }
        // }
        // if (!$startOfCurrentFlowDiff) { // DRAFTに戻った記録がなければ、最初のPENDINGが起点
        //     $startOfCurrentFlowDiff = $historyAsc->whereIn('status', [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])
        //                                        ->sortBy('id')->first();
        // }
        // // 起点Diffの一つ前でcontentがあるものを探す
        // if ($startOfCurrentFlowDiff) {
        //     return $historyAsc->where('id', '<', $startOfCurrentFlowDiff->id)
        //                     ->whereNotNull('content')->where('content', '<>', '[]')
        //                     ->last();
        // }
        // return null; // それでも見つからなければ比較対象なし
    }

    // --- コメント入力モーダルを開く ---
    /*    public function openCommentModal(string $actionType): void
        {
            $this->actionTypeForModal = $actionType;
            $this->commentForModal = ''; // コメントをリセット
            $this->resetValidation('commentForModal'); // バリデーションエラーをクリア
            $this->showCommentModal = true;
        }*/

    public function openCommentModal(string $actionType): void
    {
        $title = '';
        $actionLabel = '';
        $actionClass = '';
        // 差し戻し時はコメント必須にするか等のロジックもここやモーダル側で制御可能
        // $isCommentRequired = $actionType === 'return_to_draft';

        switch ($actionType) {
            case 'approve':
                $title = __('ledger.workflow.approve') . ' - ' . __('ledger.workflow.comments');
                $actionLabel = __('ledger.workflow.approve');
                $actionClass = 'btn-primary';
                break;
            case 'return_to_draft':
                $title = __('ledger.workflow.return_to_draft') . ' - ' . __('ledger.workflow.comments');
                $actionLabel = __('ledger.workflow.return_to_draft');
                $actionClass = 'btn-warning';
                break;
            case 'request_approval_with_comment': // 点検完了(承認申請)でコメントを付けたい場合
                if (is_null($this->selectedApproverId)) { // 先に担当者選択が必要
                    $this->warning(__('ledger.workflow.select_approver_first'));
                    $this->openApproverSelectModal(); // 担当者選択モーダルを開く
                    return;
                }
                $title = __('ledger.workflow.request_approval_short') . ' - ' . __('ledger.workflow.comments');
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
                initialComment: '' // 必要なら以前のコメントなどを渡す
            );
        }
    }

    // --- コメント付きでアクション実行 ---
    public function executeActionWithComment(): void
    {
        $this->validate(['commentForModal' => 'nullable|string|max:1000']); // コメントのバリデーション

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
            // 必要に応じて他のアクションタイプも追加
            // (例: request_approval_with_comment)

            $this->loadWorkflowHistory(); // 履歴を更新
            $this->prepareContentDiff();  // 差分情報も更新
        } catch (\Exception $e) {
            Log::error("Workflow action '{$this->actionTypeForModal}' failed: " . $e->getMessage());
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
            'approve' => __('ledger.workflow.approve') . ' - ' . __('ledger.workflow.comments'),
            'return_to_draft' => __('ledger.workflow.return_to_draft') . ' - ' . __('ledger.workflow.comments'),
            default => __('ledger.workflow.comments'),
        };
    }

    public function getCommentModalActionLabel(): string
    {
        return match ($this->actionTypeForModal) {
            'approve' => __('ledger.workflow.approve'),
            'return_to_draft' => __('ledger.workflow.return_to_draft'),
            default => __('Execute'), // 翻訳キー: actions.execute
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

    #[On('workflow-action-with-comment')] // イベントリスナー
    public function handleActionWithComment(string $actionType, int $ledgerId, ?string $comment): void
    {
        if ($ledgerId !== $this->ledgerRecord->id) return; // 対象が異なる場合は無視

        $this->commentForModal = $comment ?? ''; // モーダルが閉じた後なので、再利用はしないが一応
        $modifierId = Auth::id();

        try {
            if ($actionType === 'approve') {
                if (!$this->canApprove()) throw new \Exception(__('messages.error.unauthorized'));
                $this->ledgerRecord = $this->workflowService->approve($ledgerId, $modifierId, $comment);
                $this->success(__('ledger.workflow.approved_message'));
            } elseif ($actionType === 'return_to_draft') {
                if (!$this->canReturnToDraft()) throw new \Exception(__('messages.error.unauthorized'));
                $this->ledgerRecord = $this->workflowService->returnToDraft($ledgerId, $modifierId, $comment);
                $this->success(__('ledger.workflow.returned_to_draft_message'));
            } elseif ($actionType === 'request_approval_with_comment') {
                if (!$this->canRequestApproval() || is_null($this->selectedApproverId)) {
                    throw new \Exception(__('messages.error.unauthorized_or_missing_approver'));
                }
                // WorkflowService の requestApproval もコメントを受け取るように修正が必要
                $this->ledgerRecord = $this->workflowService->requestApproval($ledgerId, $this->selectedApproverId, $modifierId, $comment);
                $this->success(__('ledger.workflow.approval_requested_message'));
            }
            $this->loadWorkflowHistory();
            $this->prepareContentDiff();
        } catch (\Exception $e) { /* ... */
        } finally {
            $this->selectedApproverId = null;
        }
    }
}
