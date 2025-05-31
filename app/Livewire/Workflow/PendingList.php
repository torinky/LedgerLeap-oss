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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
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
    public bool $showAssigneeModal = false; // 承認者選択モーダル用
    public string $assigneeModalRoleType = 'approver'; // 固定
    public ?int $modalLedgerId = null; // <<<--- モーダルに渡す Ledger ID を保持
    public ?int $modalLedgerDefineId = null;
    public ?int $modalFolderId = null;

    // ------------------------------

    public ?int $selectedLedgerDiffId = null; // <<<--- 戻しモーダル用に Diff ID を保持

    public int $totalPendingTasks = 0; //  合計件数用プロパティ

    public array $returnComments = []; // 戻し理由コメント (タスクIDをキーにする)
    /**
     * @var mixed|null
     */
    private mixed $workflowService;
    /**
     * @var mixed|null
     */
    public string $comments='';

    public function boot(WorkflowTaskRepository $repository, WorkflowService $workflowService): void
    {
        $this->taskRepository = $repository;
        $this->workflowService = $workflowService;
    }

    public function render()
    {
        $pendingTasks = $this->taskRepository->getPendingTasksForUser(Auth::user());
        $this->totalPendingTasks = $pendingTasks->total(); // ページネーション結果から合計を取得

        // ---件数をイベントで親に通知 ---
        $this->dispatch('update-tab-count', tab: 'tasks', count: $this->totalPendingTasks);

        // --- 各タスクに進捗情報を追加 ---
        $pendingTasks->getCollection()->transform(function (Ledger $ledger) {
            if ($ledger->define?->workflow_enabled && $ledger->define?->folder) {
                $progress = $ledger->getRequiredRolesProgressDetails();
                $ledger->required_roles_progress_summary = [ // 新しいプロパティとして追加
                    'inspection_completed' => $progress['inspection']['completed_count'],
                    'inspection_total' => $progress['inspection']['total_count'],
                    'inspection_all_completed' => $progress['inspection']['is_all_completed'],
                    'inspection_pending_roles_names' => $progress['inspection']['pending_roles']->pluck('name'),
                    'inspection_completed_roles_names' => $progress['inspection']['completed_roles']->pluck('name'),
                    'approval_completed' => $progress['approval']['completed_count'],
                    'approval_total' => $progress['approval']['total_count'],
                    'approval_all_completed' => $progress['approval']['is_all_completed'],
                    'approval_pending_roles_names' => $progress['approval']['pending_roles']->pluck('name'),
                    'approval_completed_roles_names' => $progress['approval']['completed_roles']->pluck('name'),
                ];
            } else {
                $ledger->required_roles_progress_summary = null; // ワークフロー無効ならnull
            }
            return $ledger;
        });
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
/*    public function openApprovalRequestModal(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
        $this->selectedApproverId = null; // 選択肢をリセット
//        $this->loadApproverOptions($taskId); // 承認者候補をロード
        if (empty($this->approverOptions)) {
            $this->error(__('ledger.workflow.no_approvers_found'));
            return;
        }
        $this->approvalRequestModal = true; // <<<--- モーダル表示プロパティを true に
    }*/


    /**
     * 承認者の選択肢をロードする
     */
//    public function loadApproverOptions(int $taskId): void
//    {
//        $ledgerDiff = LedgerDiff::with('ledger.define')->find($this->selectedTaskId);
//        if (!$ledgerDiff || !$ledgerDiff->ledger) {
//            $this->approverOptions = [];
//            return;
//        }
//        $ledgerDefine = $ledgerDiff->ledger->define;
//
//        $options = [];
//        // 推奨ユーザー
//        if ($ledgerDefine?->recommendedApprover) {
//            $approver = $ledgerDefine->recommendedApprover;
//            $options[$approver->id] = ['id' => $approver->id, 'name' => $approver->name . ' (' . __('ledger.workflow.recommended_user') . ')'];
//            $this->selectedApproverId = $approver->id; // デフォルト選択
//        }
//        // 推奨ロール
//        if ($ledgerDefine?->recommendedApproverRole) {
//            $roleUsers = $ledgerDefine->recommendedApproverRole->users()->orderBy('name')->get();
//            foreach ($roleUsers as $user) {
//                if (!isset($options[$user->id])) { // 重複チェック
//                    $options[$user->id] = ['id' => $user->id, 'name' => $user->name . ' (' . __('ledger.workflow.recommended_role') . ')'];
//                    if (!$this->selectedApproverId) $this->selectedApproverId = $user->id; // ロールが最初ならデフォルト選択
//                }
//            }
//        }
//        // その他の全ユーザー (必要に応じてフィルタリング)
//        // 例: $allUsers = User::where('is_active', true)->orderBy('name')->get();
//        $allUsers = User::orderBy('name')->get();
//        foreach ($allUsers as $user) {
//            if (!isset($options[$user->id])) { // 重複チェック
//                $options[$user->id] = ['id' => $user->id, 'name' => $user->name];
//            }
//        }
//
//        $this->approverOptions = array_values($options);
//    }

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
                Auth::id(), // 点検者ID
                $this->comments
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
/*    public function openReturnToDraftModal(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
        // 配列キーが存在しない場合のエラーを防ぐため、初期化
        if (!isset($this->returnComments[$taskId])) {
            $this->returnComments[$taskId] = '';
        }
        $this->returnToDraftModal = true;
    }*/
    /**
     * 作成中に戻すモーダルを開く (引数を LedgerDiff ID に変更)
     */
    public function openReturnToDraftModal(int $ledgerDiffId): void // <<<--- 引数変更
    {
        $this->selectedLedgerDiffId = $ledgerDiffId; // <<<--- プロパティにセット
        if (!isset($this->returnComments[$ledgerDiffId])) {
            $this->returnComments[$ledgerDiffId] = '';
        }
        $this->returnToDraftModal = true;
    }

    /**
     * タスクを作成中に戻す
     */
//    public function returnTaskToDraft(): void
//    {
//        if ($this->selectedTaskId === null) {
//            $this->error('No task selected.'); // エラー処理
//            return;
//        }
//
//        // コメントを取得 (null の可能性もある)
//        $comments = $this->returnComments[$this->selectedTaskId] ?? null;
//
//        // コメント必須の場合のバリデーション (任意)
//        // $validated = $this->validate(['returnComments.'.$this->selectedTaskId => ['required', 'string', 'max:1000']]);
//        // $comments = $validated['returnComments'][$this->selectedTaskId];
//
//        $ledgerDiff = LedgerDiff::find($this->selectedTaskId);
//        if (!$ledgerDiff) {
//            $this->error('Task not found.');
//            return;
//        }
//
//        // 権限チェック (簡易) - Service 側でもチェック推奨
//        $canReturn = ($ledgerDiff->status === WorkflowStatus::PENDING_INSPECTION && $ledgerDiff->inspector_id === Auth::id()) ||
//            ($ledgerDiff->status === WorkflowStatus::PENDING_APPROVAL && $ledgerDiff->approver_id === Auth::id());
//
//        if (!$canReturn) {
//            $this->error(__('messages.error.unauthorized'));
//            return;
//        }
//
//        try {
//            // WorkflowService の returnToDraft メソッドを呼び出す
//            $this->workflowService->returnToDraft($ledgerDiff->ledger_id, Auth::id(), $comments);
//
//            $this->returnToDraftModal = false; // モーダルを閉じる
//            $this->success(__('ledger.workflow.returned_to_draft_message'));
////            $this->dispatch('$refresh'); // リストを更新
//        } catch (\Exception $e) {
//            Log::error("Return to draft failed for task ID {$this->selectedTaskId}: " . $e->getMessage());
//            $this->error(__('messages.error.generic'));
//        } finally {
//            // コメントと選択タスクIDをリセット
//            unset($this->returnComments[$this->selectedTaskId]);
//            $this->selectedTaskId = null;
//            $this->returnToDraftModal = false;
//
//        }
//    }
    /**
     * タスクを作成中に戻す (引数を LedgerDiff ID に変更)
     */
    public function returnTaskToDraft(): void
    {
        if ($this->selectedLedgerDiffId === null) { $this->error('No task selected.'); return; }
        $comments = $this->returnComments[$this->selectedLedgerDiffId] ?? null;
        $ledgerDiff = LedgerDiff::find($this->selectedLedgerDiffId); // <<<--- ID で検索
        if (!$ledgerDiff) { $this->error('Task not found.'); return; }

        // 権限チェック (変更なし)
        $canReturn = ($ledgerDiff->status === WorkflowStatus::PENDING_INSPECTION && $ledgerDiff->inspector_id === Auth::id()) ||
            ($ledgerDiff->status === WorkflowStatus::PENDING_APPROVAL && $ledgerDiff->approver_id === Auth::id());
        if (!$canReturn) { $this->error(__('messages.error.unauthorized')); return; }

        try {
            $this->workflowService->returnToDraft($ledgerDiff->ledger_id, Auth::id(), $comments); // <<<--- ledger_id を使用
            $this->returnToDraftModal = false;
            $this->success(__('ledger.workflow.returned_to_draft_message'));
            $this->dispatch('$refresh'); // リスト更新
        } catch (\Exception $e) {
            Log::error("Return to draft failed from PendingList for Diff ID {$this->selectedLedgerDiffId}: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        } finally {
            unset($this->returnComments[$this->selectedLedgerDiffId]);
            $this->selectedLedgerDiffId = null;
            $this->returnToDraftModal = false;
        }
    }

    /**
     * 承認者選択モーダルを開く
     */
    public function openApproverSelectModal(int $ledgerId): void // <<<--- Ledger ID を受け取る
    {
        $ledger = Ledger::with('define:id,folder_id')->find($ledgerId); // Define ID と Folder ID も取得
        if (!$ledger || !$ledger->define) { $this->error(__('Task not found.')); return; }
        $latestDiff = $ledger->latestDiff()->first();

        // 権限チェック (自分が点検者か？)
        if ($ledger->status !== WorkflowStatus::PENDING_INSPECTION || Auth::id() !== $latestDiff?->inspector_id) {
            $this->error(__('messages.error.unauthorized'));
            return;
        }

        $this->modalLedgerId = $ledgerId; // <<<--- プロパティにセット
        $this->modalLedgerDefineId = $ledger->ledger_define_id;
        $this->modalFolderId = $ledger->define->folder_id;
        $this->assigneeModalRoleType = 'approver';
        $initialApproverId = $this->getInitialApproverId($ledger->ledger_define_id); // <<<--- 初期選択ID取得

        $this->resetValidation();
        $this->showAssigneeModal = true;

        $this->dispatch('open-assignee-modal',
            ledgerDefineId: $this->modalLedgerDefineId,
            folderId: $this->modalFolderId,
            roleType: 'approver',
            ledgerId: $this->modalLedgerId, // <<<--- Ledger ID を渡す
            initialUserId: $initialApproverId
        );
    }


    // --- loadApproverOptions は削除 ---

    /**
     * モーダルから承認者が選択されたときのイベントリスナー
     */
    #[On('assignee-selected')]
    public function handleAssigneeSelected(int $userId, string $roleType): void
    {
        if ($roleType !== 'approver' || $this->modalLedgerId === null) {
            return;
        }
        Log::debug("Approver selected via modal for Ledger ID {$this->modalLedgerId}: User ID {$userId}");
        $this->requestApprovalInternal($userId);

        $this->showAssigneeModal = false;
        $this->modalLedgerId = null; // リセット
        $this->modalLedgerDefineId = null;
        $this->modalFolderId = null;
    }

    /**
     * 承認申請を実行する内部メソッド
     */
    protected function requestApprovalInternal(int $approverId): void
    {
        $ledger = Ledger::find($this->modalLedgerId); // モーダルに渡したIDを使用
        if (!$ledger) {
            $this->error(__('ledger.workflow.ledger_not_found'));
            Log::error('Approval request failed from PendingList: Ledger not found.'.$this->modalLedgerId);
            return;
        }
        $latestDiff = $ledger->latestDiff()->first();

        // 権限チェック (再確認)
        if ($ledger->status !== WorkflowStatus::PENDING_INSPECTION || Auth::id() !== $latestDiff?->inspector_id) {
            $this->error(__('messages.error.unauthorized')); return;
        }
        if(!User::find($approverId)){ $this->error(__('無効な担当者が選択されました。')); return; }

        try {
            $this->workflowService->requestApproval(
                $this->modalLedgerId,
                $approverId,
                Auth::id(),
                $this->comments
            );
            $this->success(__('ledger.workflow.approval_requested_message'));
            // リスト更新のために $this->render() を再実行させるか、リフレッシュイベントを発行
            $this->dispatch('$refresh'); // 自分自身をリフレッシュ
        } catch (Exception $e) {
            Log::error("Approval request failed from PendingList: " . $e->getMessage());
            $this->error(__('messages.error.generic'));
        }
    }

    // --- 承認者の初期選択IDを取得するヘルパー ---
    protected function getInitialApproverId($ledgerDefineId): ?int
    {
        // 実績ベースで取得 (CreateColumn と同じロジック)
        $frequentUsers = $this->workflowService->getFrequentAssignees($ledgerDefineId, 'approver', 1);
        return !empty($frequentUsers) ? $frequentUsers[0]['id'] : null;
    }

    #[On('refreshPendingList')] // イベントをリッスン
    public function refreshList(): void
    {
        // render メソッドが再実行されればリストは更新されるので、
        // 明示的にデータを再ロードする必要はないかもしれないが、
        // 確実に最新状態にするために render を再呼び出しするか、
        // データ取得メソッドがあればそれを呼び出す。
        // ここでは、次のレンダリングサイクルでデータが更新されることを期待。
        // もし明示的にデータ再取得が必要なら、render 内のデータ取得ロジックをメソッドに切り出して呼び出す。
        // 例: $this->loadPendingTasks();
        // $this->dispatch('$refresh'); // これでも可
        $this->render(); // 強制的に再描画 (Livewireのバージョンや実装による)
        Log::info("PendingList refreshed by event.");
    }

}
