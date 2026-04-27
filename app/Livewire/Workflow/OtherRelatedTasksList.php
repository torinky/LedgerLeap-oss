<?php

namespace App\Livewire\Workflow;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Livewire\BaseLivewireComponent;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\User;
use App\Services\UserService;
use App\Services\WorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
// Eloquent Collection
// Support Collection
use Illuminate\Support\Facades\Log;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Mary\Traits\Toast;

class OtherRelatedTasksList extends BaseLivewireComponent
{
    use Toast, WithoutUrlPagination, WithPagination;

    public int $perPage = 10;

    public string $sortField = 'ledger_updated_at'; // ソート対象を明確に

    public string $sortDirection = 'desc';

    // 統合されたタスクリスト (整形済みデータの Collection)
    public BaseCollection $tasksData; // Eloquent Collection ではなく Support Collection

    protected UserService $userService;

    protected WorkflowService $workflowService;

    // 引き継ぎ関連のプロパティとメソッドは後ほど
    public bool $showClaimCommentModal = false;

    public ?array $claimingTaskData = null; // 整形済みタスクデータを保持

    public string $claimComment = '';

    public $claimingTask;

    public function boot(WorkflowService $workflowService, UserService $userService): void
    {
        $this->workflowService = $workflowService;
        $this->userService = $userService;
    }

    public function mount(): void
    {
        $this->loadTasks();
    }

    public function loadTasks(): void
    {
        $user = Auth::user();
        if (! $user) {
            $this->tasksData = collect();

            return;
        }

        // 各リストを取得
        $mySubmissionsPendingOthers = $this->fetchMySubmissionsPendingOthers($user);
        $claimableTasks = $this->fetchClaimableTasks($user);

        // 結合し、重複を除去し、ソート
        $this->tasksData = $mySubmissionsPendingOthers
            ->concat($claimableTasks)
            ->unique('ledger_id') // 整形後の配列のキーで重複排除
            ->sortBy([
                [$this->sortField, $this->sortDirection],
                ['ledger_id', 'desc'],
            ])
            ->values();
    }

    /**
     * タスクデータをビュー表示用に整形する
     */
    private function formatTaskData(Ledger $ledger, string $taskType): array
    {
        $progressDetails = [];
        //        dd($ledger,$ledger->define?->workflow_enabled,$ledger->define?->folder);
        if ($ledger->define?->workflow_enabled && $ledger->define?->folder) {
            $progress = $ledger->getRequiredRolesProgressDetails();
            $progressDetails = [
                'inspection_completed' => $progress['inspection']['completed_count'],
                //                'inspection_roles_count' => $progress['inspection']['pending_roles']->count(),
                'inspection_total' => $progress['inspection']['total_count'],
                'inspection_all_completed' => $progress['inspection']['is_all_completed'],
                'inspection_pending_roles_names' => $progress['inspection']['pending_roles']->pluck('name'),
                'inspection_completed_roles_names' => $progress['inspection']['completed_roles']->pluck('name'),
                //                'pending_approval_roles_count' => $progress['approval']['pending_roles']->count(),
                'approval_completed' => $progress['approval']['completed_count'],
                'approval_total' => $progress['approval']['total_count'],
                'total_approval_roles_count' => $progress['approval']['total_count'],
                'approval_all_completed' => $progress['approval']['is_all_completed'],
                'approval_pending_roles_names' => $progress['approval']['pending_roles']->pluck('name'),
                'approval_completed_roles_names' => $progress['approval']['completed_roles']->pluck('name'),
            ];
        }

        return [
            'ledger_id' => $ledger->id,
            'tenant_id' => $ledger->tenant_id,
            'ledger_title' => $ledger->define?->title ?? __('ledger.unknown_ledger'),
            'status_value' => $ledger->status->value,
            'status_label' => $ledger->status->label(),
            'status_color_class' => $ledger->status->colorClass(),
            'current_inspector_name' => $ledger->latestDiff?->inspector?->name,
            'current_approver_name' => $ledger->latestDiff?->approver?->name,
            'applicant_name' => $ledger->creator?->name,
            'ledger_updated_at' => $ledger->updated_at,
            'ledger_created_at' => $ledger->created_at,
            'task_type' => $taskType,
            'is_locked' => $ledger->isLocked(),
            'required_roles_progress_summary' => ! empty($progressDetails) ? $progressDetails : null,
        ];
    }

    /**
     * ユーザーが特定のタスクを引き継ぎ可能か判定する
     */
    protected function canUserClaimTask(Ledger $ledger, User $user): bool
    {
        if (! $ledger->status->isWorkflowPending()) {
            return false;
        } // 進行中でないと不可
        if ($ledger->creator_id === $user->id) {
            return false;
        } // 申請者は引き継げない
        if ($ledger->latestDiff?->inspector_id === $user->id || $ledger->latestDiff?->approver_id === $user->id) {
            return false;
        } // 担当者は引き継げない

        // ユーザーがそのタスクのフォルダに対して点検または承認権限を持っているか
        $requiredPermission = ($ledger->status === WorkflowStatus::PENDING_INSPECTION) ? FolderPermissionType::INSPECT : FolderPermissionType::APPROVE;

        return $ledger->define?->folder && $this->userService->hasFolderPermission($user, $ledger->define->folder, $requiredPermission);
    }

    public function sortBy($field): void
    {
        // ソート対象のフィールドを調整 (例: 'ledger_updated_at')
        $actualSortField = $field;
        if ($field === 'updated_at_formatted') {
            $actualSortField = 'ledger_updated_at';
        }
        if ($field === 'age') {
            $actualSortField = 'ledger_created_at';
        }

        if ($this->sortField === $actualSortField) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
        }
        $this->sortField = $actualSortField;
        $this->loadTasks(); // 再ソートしてロード
    }

    public function render()
    {
        // --- ここからログ出力追加 ---
        /*
                Log::debug('OtherRelatedTasksList Rendering Start');
                Log::debug('Current Page from getPage(): ' . $this->getPage());
                Log::debug('Total items in tasksData: ' . $this->tasksData->count());
                Log::debug('Items per page: ' . $this->perPage);

                $itemsForCurrentPage = $this->tasksData->forPage($this->getPage(), $this->perPage);
                Log::debug('Number of items for current page: ' . $itemsForCurrentPage->count());

                if ($this->tasksData->count() > 0 && $itemsForCurrentPage->isEmpty() && $this->getPage() > 1) {
                    Log::warning('Attempting to access a page that has no items, but tasksData is not empty. This might indicate an issue with page number or tasksData content.');
                }
        */

        $paginatedTasks = new LengthAwarePaginator(
            $this->tasksData->forPage($this->getPage('related_task_page'), $this->perPage),
            $this->tasksData->count(),
            $this->perPage,
            $this->getPage('related_task_page'),
            ['path' => request()->url(), 'query' => request()->query(), 'pageName' => 'related_task_page']
        );

        return view('livewire.workflow.other-related-tasks-list', [
            'listedTasks' => $paginatedTasks,
        ]);
    }

    /**
     * 1. 自分が申請したが、現在他の人が担当しているタスク
     */
    private function fetchMySubmissionsPendingOthers(User $user): BaseCollection
    {
        $userId = $user->id;
        $results = collect();

        // A-1: 自分が申請し、点検待ちだが、点検者が自分ではない
        $pendingInspectionByOthers = Ledger::where('creator_id', $userId)
            ->where('status', WorkflowStatus::PENDING_INSPECTION)
            ->whereHas('latestDiff', function (Builder $query) use ($userId) {
                $query->where('inspector_id', '!=', $userId)
                    ->orWhereNull('inspector_id'); // まだ担当者が決まっていない場合
            })
            ->withNeededRelations()
            ->get()
            ->map(fn (Ledger $ledger) => $this->formatTaskData($ledger, 'my_submission_pending_inspection'));
        $results = $results->concat($pendingInspectionByOthers);

        // A-2: 自分が申請または点検し、承認待ちだが、承認者が自分ではない
        $pendingApprovalByOthers = Ledger::where('status', WorkflowStatus::PENDING_APPROVAL)
            ->where(function (Builder $query) use ($userId) {
                $query->where('creator_id', $userId) // 自分が直接承認依頼
                    ->orWhereHas('latestDiff', function (Builder $diffQuery) use ($userId) {
                        // 自分が点検完了して承認待ちにした
                        $diffQuery->where('inspector_id', $userId);
                    });
            })
            ->whereHas('latestDiff', function (Builder $query) use ($userId) {
                $query->where('approver_id', '!=', $userId)
                    ->orWhereNull('approver_id'); // まだ担当者が決まっていない場合
            })
            ->withNeededRelations()
            ->get()
            ->map(fn (Ledger $ledger) => $this->formatTaskData($ledger, 'my_submission_pending_approval'));
        $results = $results->concat($pendingApprovalByOthers);

        return $results;
    }

    /**
     * 2. 自分に処理権限があり、かつ自分が担当者でも申請者でもない、他の人のタスク (引き継ぎ可能)
     */
    private function fetchClaimableTasks(User $user): BaseCollection
    {
        return $this->userService->getClaimableTasks($user)
            ->map(fn (Ledger $ledger) => $this->formatTaskData($ledger, 'claimable'));
    }

    public function openClaimTaskCommentModal(int $ledgerId): void
    {
        // tasksData から対象のタスクデータを取得
        //        $this->loadTasks();
        $this->claimingTaskData = $this->tasksData->firstWhere('ledger_id', $ledgerId);
        //        dd($ledgerId,$this->claimingTaskData,$this->tasksData);
        if ($this->claimingTaskData) {
            $this->claimComment = '';
            $this->showClaimCommentModal = true;
        } else {
            $this->error(__('ledger.workflow.task_not_found'));
        }
    }

    /**
     * コメント付きでタスクを引き継ぐ
     */
    public function claimTaskWithComment(): void
    {
        if (! $this->claimingTaskData || ! isset($this->claimingTaskData['ledger_id'])) {
            $this->error(__('ledger.workflow.no_task_to_claim'));
            $this->showClaimCommentModal = false;

            return;
        }

        // (任意) コメントが必須であればバリデーション
        // $this->validate(['claimComment' => 'required|string|max:1000']);

        $ledgerId = $this->claimingTaskData['ledger_id'];
        $claimer = Auth::user(); // 現在のログインユーザーが引き継ぎ者
        $ledger = Ledger::find($ledgerId);

        if (! $ledger || ! $claimer) {
            $this->error(__('ledger.errors.cannot_execute_action'));
            $this->showClaimCommentModal = false;

            return;
        }

        try {
            // WorkflowService の claimTask メソッドを呼び出す
            $updatedLedger = $this->workflowService->claimTask($ledger, $claimer, $this->claimComment);

            $this->showClaimCommentModal = false;
            $this->claimingTaskData = null;
            $this->claimComment = '';
            $this->loadTasks(); // リストを再読み込みして、引き継いだタスクがここから消えることを期待
            $this->dispatch('refreshPendingList'); // 自分宛リストも更新させるイベント (PendingList側でリッスン)
            $this->success(__('ledger.workflow.task_claimed_successfully'));

        } catch (\Exception $e) {
            Log::error("Task claim failed for Ledger ID {$ledgerId} from OtherRelatedTasksList: ".$e->getMessage(), [
                'claimer_id' => $claimer->id,
                'comment' => $this->claimComment,
                'exception' => $e,
            ]);
            $this->error(__('ledger.error'), $e->getMessage());
            $this->showClaimCommentModal = false;
        }
    }
}
