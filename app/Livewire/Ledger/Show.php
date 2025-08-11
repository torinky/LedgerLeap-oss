<?php

namespace App\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
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
use App\Services\Ledger\LedgerContentProcessor; // 追加
use App\Services\Ledger\LedgerDiffProcessor; // 追加
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use AuthorizesRequests, Toast;

    public $canView = false;

    public Ledger $ledgerRecord; // タイプヒントを明確に

    public $ledgerDefineRecord; // define はリレーションでアクセス可能

    public $canUpdate = false;

    // --- Workflow Action 用 ---
    public bool $approvalRequestModal = false;

    public bool $returnToDraftModal = false;

    public ?int $selectedApproverId = null;    // 次の承認者選択用

    public bool $showAssigneeModalForNext = false; // 次の承認者選択モーダル表示用

    public string $nextAssigneeRoleType = 'approver'; // 固定

    public array $approverOptions = [];

    public string $returnComment = ''; // 詳細画面ではタスクIDは不要

    public bool $showCommentModal = false; // <<<--- コメントモーダル表示用

    public string $actionTypeForModal = ''; // <<<--- モーダルに渡すアクションタイプ

    public string $commentForModal = '';    // <<<--- モーダルとバインドするコメント

    // --- 差分表示用 ---
    public ?LedgerDiff $comparisonTargetDiff = null; // 比較対象の古いDiff

    public array $contentChanges = []; // カラムごとの変更内容

    protected WorkflowService $workflowService;
    protected LedgerContentProcessor $ledgerContentProcessor;
    protected LedgerDiffProcessor $ledgerDiffProcessor; // 追加

    // --- モーダル制御用プロパティ ---
    public bool $showAssigneeModal = false; // 承認者選択モーダル用

    protected string $assigneeModalRoleType = 'approver'; // 固定で approver

    public Collection $workflowHistory; // ワークフロー履歴用プロパティ

    public ?Collection $currentLedgerAttachments = null; // ★ 追加

    public $selectedTab = 'details';

    // WorkflowService をインジェクト
    public bool $hasChangedColumns = false;

    public bool $showChanges = false;

    public array $requiredRolesProgress = []; //  必須ロール進捗情報

    #[Url(as: 'dl')]
    public int $displayLevel = 1;

    public array $collapsedStates = [];

    public array $filteredColumns = []; // ★ New public property
    public array $displayColumns = []; // 追加

    public function boot(WorkflowService $workflowService, LedgerContentProcessor $ledgerContentProcessor, LedgerDiffProcessor $ledgerDiffProcessor): void
    {
        $this->workflowService = $workflowService;
        $this->ledgerContentProcessor = $ledgerContentProcessor;
        $this->ledgerDiffProcessor = $ledgerDiffProcessor; // 追加
    }

    public function updatedDisplayLevel(int $level): void
    {
        // displayLevel は wire:model.live で既に更新されている
        // ここでは filteredColumns を再計算するだけで良い
        $this->filteredColumns = $this->calculateFilteredColumns();
    }

    public function setDisplayLevel(int $level): void
    {
        if (in_array($level, [1, 2, 3])) {
            $this->displayLevel = $level;
            $this->filteredColumns = $this->calculateFilteredColumns(); // ★ Recalculate
        }
    }

    protected function calculateFilteredColumns(): array
    {
        if (empty($this->ledgerDefineRecord) || empty($this->ledgerDefineRecord->column_define)) {
            return [];
        }

        return collect($this->ledgerDefineRecord->column_define)
            ->filter(function ($column) {
                $columnDisplayLevel = is_array($column) ? ($column['display_level'] ?? 3) : ($column->display_level ?? 3);
                return $columnDisplayLevel <= $this->displayLevel;
            })
            ->sortBy(function($column) {
                return is_array($column) ? $column['order'] : $column->order;
            })
            ->map(function ($column) {
                // Explicitly create a simple associative array from ColumnDefine object
                // This ensures Livewire can serialize it.
                return [
                    'id' => $column->id,
                    'name' => $column->name,
                    'type' => $column->type,
                    'order' => $column->order,
                    'useOptions' => $column->useOptions,
                    'options' => $column->options,
                    'required' => $column->required,
                    'unique' => $column->unique,
                    'sortBy' => $column->sortBy,
                    'hint' => $column->hint,
                    'file' => $column->file,
                    'display_level' => $column->display_level,
                    'group' => $column->group,
                ];
            })
            ->all();
    }

    // WorkflowService をインジェクト
    public function mount(int $ledgerId): void
    {
        $this->ledgerRecord = Ledger::with([
            'define',
            'modifier:id,name',
            'creator:id,name',
            'latestDiff.inspector:id,name',
            'latestDiff.approver:id,name',
        ])
            ->findOrFail($ledgerId);
        $this->ledgerDefineRecord = $this->ledgerRecord->define;

        $this->currentLedgerAttachments = AttachedFile::where('ledger_id', $this->ledgerRecord->id)->get();

        $this->loadWorkflowHistory();
        $this->prepareContentDiff();

        if ($this->ledgerDefineRecord->workflow_enabled && $this->ledgerRecord->define?->folder) {
            $this->requiredRolesProgress = $this->ledgerRecord->getRequiredRolesProgressDetails();
        }

        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord]);

        if (!in_array($this->displayLevel, [1, 2, 3])) {
            $this->displayLevel = 1;
        }

        $this->filteredColumns = $this->calculateFilteredColumns(); // ★ Calculate on mount

        // Initialize collapsedStates: all groups are open by default.
        $allGroups = collect($this->ledgerDefineRecord->column_define)
            ->pluck('group')
            ->filter() // null/empty のグループ名を除外
            ->unique()
            ->toArray();

        foreach ($allGroups as $groupName) {
            $this->collapsedStates[$groupName] = false; // デフォルトで開く
        }
        $this->collapsedStates['その他'] = false; // デフォルトで開く

        // 必須項目を含むグループは常に開く（このロジックは不要になるが、念のため残す）
        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $columnObject = is_array($column) ? new \App\Models\ColumnDefine($column) : $column;
            if ($columnObject->required) {
                $groupName = $columnObject->group ?? ''; // デフォルトグループは空文字列として扱う
                $this->collapsedStates[$groupName] = false; // 必須項目を含むグループは開く
            }
        }
    }

    

    public function toggleGroup(string $groupName): void
    {
        // If the group doesn't exist in collapsedStates, initialize it to false (open)
        if (!isset($this->collapsedStates[$groupName])) {
            $this->collapsedStates[$groupName] = false;
        }
        $this->collapsedStates[$groupName] = !$this->collapsedStates[$groupName];
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
        if ($this->actionTypeForModal == 'approve_and_select_next') {
            $this->handleNextApproverSelected($userId, $roleType); // <<<--- handleNextApproverSelected を呼び出し

            return;
        }
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
        if (!$this->ledgerDefineRecord) {
            return;
        }

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

    public function approveTask(): void
    {
        if (!$this->canApprove()) { // ここで canBeFinallyApproved() が呼ばれる
            $this->error(__('messages.error.unauthorized_or_conditions_not_met')); // 翻訳キー例

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
            ledgerDefineId: $this->ledgerDefineRecord->id,
            folderId: $this->ledgerDefineRecord->folder_id,
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
    //    #[On('assignee-selected')] // 既存のリスナーと競合しないように、または条件分岐
    public function handleNextApproverSelected(int $userId, string $roleType): void
    {
        //        dd($roleType, $this->actionTypeForModal);
        if ($this->actionTypeForModal !== 'approve_and_select_next' || $roleType !== 'approver') {
            return;
        }
        Log::debug("Next Approver selected: {$userId} for Ledger ID {$this->ledgerRecord->id}");

        try {
            // 保持しておいたコメントと、選択された次の承認者IDを使って承認処理を実行
            $this->ledgerRecord = $this->workflowService->approve(
                $this->ledgerRecord->id,
                Auth::id(), // 今回の承認アクション実行者
                $this->commentForModal,
                $userId // 次の承認者
            );
            $this->success(__('あなたの承認処理は完了し、次の承認者に依頼されました。')); // 翻訳キー

            $this->loadWorkflowHistory();
            $this->prepareContentDiff();
            $this->mount($this->ledgerRecord->id); // ★ mount を呼び出し

        } catch (\Exception $e) {
            Log::error('Finalizing approval with next assignee failed: ' . $e->getMessage());
            $this->error(__('messages.error.generic'), $e->getMessage());
        } finally {
            $this->commentForModal = '';
            $this->actionTypeForModal = '';
            $this->selectedApproverId = null; // 使用後クリア
        }
    }

    // --- 次の承認者の初期選択IDを取得 (自分と現在の承認担当者を除く) ---
    protected function getInitialApproverIdExcludingSelfAndCurrent(): ?int
    {
        $excludeIds = [$this->ledgerRecord->latestDiff?->approver_id, Auth::id()];
        $frequentUsers = $this->workflowService->getFrequentAssignees($this->ledgerDefineRecord->id, 'approver', 5, '', $excludeIds);

        return !empty($frequentUsers) ? $frequentUsers[0]['id'] : null;
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

    public function render()
    {
//        Log::debug('Show.php render() - hasChangedColumns: ' . ($this->hasChangedColumns ? 'true' : 'false') . ', showChanges: ' . ($this->showChanges ? 'true' : 'false'));
        $filteredColumns = [];
        if (!empty($this->ledgerDefineRecord) && !empty($this->ledgerDefineRecord->column_define)) {
            $filteredColumns = collect($this->ledgerDefineRecord->column_define)
                ->filter(function ($column) {
                    // display_level がない場合はデフォルトで 3 (詳細) として扱う
                    $columnDisplayLevel = is_array($column) ? ($column['display_level'] ?? 3) : ($column->display_level ?? 3);
                    return $columnDisplayLevel <= $this->displayLevel;
                })
                ->sortBy(function($column) {
                    return is_array($column) ? $column['order'] : $column->order;
                })
                ->all();
        }

        // フィルタリングされたカラムを 'group' プロパティでグループ化
        $groupedColumns = collect($this->filteredColumns)
            ->groupBy(function ($column) {
                // 'group' プロパティを使用し、null/empty の場合は空文字列をデフォルトとする
                $group = is_array($column) ? ($column['group'] ?? '') : ($column->group ?? '');
                return $group === '' ? __('ledger.form.group_default') : $group; // デフォルトグループ名には翻訳を使用
            })
            ->sortBy(function ($columns, $groupName) {
                // グループを最初のカラムの order でソート、order がなければグループ名でアルファベット順にソート
                if ($columns->isNotEmpty()) {
                    $firstColumn = $columns->first();
                    return is_array($firstColumn) ? ($firstColumn['order'] ?? PHP_INT_MAX) : ($firstColumn->order ?? PHP_INT_MAX);
                }
                return $groupName; // groupBy で空のグループは発生しないはずだが、念のため
            });

        // LedgerContentProcessor を使用して displayColumns を生成
        $this->displayColumns = $this->ledgerContentProcessor->processContentForDisplay(
            $this->ledgerRecord,
            $this->ledgerDefineRecord
        );

        return view('livewire.ledger.show', [
            'groupedColumns' => $groupedColumns, // グループ化されたカラムをビューに渡す
            'filteredColumns' => $this->filteredColumns, // 差分表示ロジックのために残す
            'displayColumns' => $this->displayColumns, // ビューに渡す
        ])->layout('layouts.app');
    }

    /**
     * 差分表示のためのデータを準備する
     */
    protected function prepareContentDiff(): void
    {
        Log::debug('prepareContentDiff() started.');
        // LedgerDiffProcessor を使用して comparisonTargetDiff を取得
        $this->comparisonTargetDiff = $this->ledgerDiffProcessor->findComparisonTargetDiff($this->ledgerRecord);
        Log::debug('prepareContentDiff() - comparisonTargetDiff exists: ' . ($this->comparisonTargetDiff ? 'true' : 'false'));

        // LedgerDiffProcessor を使用して contentChanges と hasChangedColumns を取得
        $diffResult = $this->ledgerDiffProcessor->prepareContentDiff(
            $this->ledgerRecord,
            $this->ledgerDefineRecord,
            $this->comparisonTargetDiff
        );

        $this->contentChanges = $diffResult['contentChanges'];
        $this->hasChangedColumns = $diffResult['hasChangedColumns'];
    }

    public function openCommentModal(string $actionType): void
    {
        $title = '';
        $actionLabel = '';
        $actionClass = '';
        $text = '';

        // 差し戻し時はコメント必須にするか等のロジックもここやモーダル側で制御可能
        // $isCommentRequired = $actionType === 'return_to_draft';

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
                //                $text = __('ledger.workflow.confirm_edit_while_pending_text');
                break;
            case 'request_approval_with_comment': // 点検完了(承認申請)でコメントを付けたい場合
                if (is_null($this->selectedApproverId)) { // 先に担当者選択が必要
                    $this->warning(__('ledger.workflow.select_approver_first'));
                    $this->openApproverSelectModal(); // 担当者選択モーダルを開く

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
                initialComment: '', // 必要なら以前のコメントなどを渡す
                text: $text
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
        if ($ledgerId !== $this->ledgerRecord->id) {
            return;
        } // 対象が異なる場合は無視

        $this->commentForModal = $comment ?? ''; // モーダルが閉じた後なので、再利用はしないが一応
        $modifierId = Auth::id();

        try {
            if ($actionType === 'approve') {

                // ★ 承認アクションの実行
                if (! $this->canApprove()) {
                    throw new \Exception(__('messages.error.unauthorized'));
                }

                // サービスを呼び出す前に、この承認で最終承認になるか、次の承認者が必要か判定
                $progress = $this->ledgerRecord->getRequiredRolesProgressDetails();
                // 今回の承認者($modifierId)が属する必須承認ロールが完了したと仮定して判定
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
                    // 全て完了するので、最終承認として実行
                    $this->ledgerRecord = $this->workflowService->approve($ledgerId, $modifierId, $comment, null); // nextApproverId は null
                    $this->success(__('ledger.workflow.approved_message'));
                } else {
                    // まだ他の必須承認ロールが残っているので、次の承認者を選択させる
                    $this->commentForModal = $comment; // コメントを保持
                    $this->actionTypeForModal = 'approve_and_select_next'; // 新しいアクションタイプ
                    $this->openNextApproverSelectModal(); // 次の承認者選択モーダルを開く

                    return; // ここで処理を中断し、モーダルからの結果を待つ
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
                // WorkflowService の requestApproval もコメントを受け取るように修正が必要
                $this->ledgerRecord = $this->workflowService->requestApproval($ledgerId, $this->selectedApproverId, $modifierId, $comment);
                $this->success(__('ledger.workflow.approval_requested_message'));
            }
            $this->loadWorkflowHistory();
            $this->prepareContentDiff();
            // mount を呼び出して $requiredRolesProgress を再計算・再表示させる
            $this->mount($this->ledgerRecord->id); // ★ mount を呼び出し

        } catch (\Exception $e) {
            Log::error("Workflow action '{$actionType}' failed: ".$e->getMessage());
            $this->error(__('messages.error.generic'), $e->getMessage());

        } finally {
            $this->selectedApproverId = null;
        }
    }

    /**
     * 添付ファイルの処理を再試行する
     */
    public function retryProcessing(int $attachedFileId): void
    {
        try {
            $attachedFile = AttachedFile::findOrFail($attachedFileId);
            $attachedFile->retryProcessing();

            $this->success(__('file.status.retry_success'));

        } catch (\Exception $e) {
            Log::error("AttachedFile retryProcessing failed for ID: {$attachedFileId}. Error: " . $e->getMessage());
            $this->addError('retryProcessing', __('file.status.retry_failed'));
        }

        // UIを更新
        $this->mount($this->ledgerRecord->id);
    }

    public function deleteAttachedFile(int $fileId): void
    {
        try {
            $attachedFile = AttachedFile::findOrFail($fileId);

            // Gateなどでの認可チェックをここに実装
            // Gate::authorize('delete', $attachedFile);

            $attachedFile->delete();

            $this->dispatch('mary-toast', message: __('file.delete_success'), title: 'Success', type: 'success');
        } catch (\Exception $e) {
            Log::error('Failed to delete attached file: '.$e->getMessage());
            $this->error(__('file.delete_failed'));
        }

        // UIを更新
        $this->mount($this->ledgerRecord->id);
    }
}