<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Traits\Date;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowService
{
    protected NotificationService $notificationService; // NotificationService をインジェクト
    protected UserService $userService; // UserService もインジェクトする想定


    public function __construct(NotificationService $notificationService, UserService $userService) // コンストラクタでインジェクト
    {
        $this->notificationService = $notificationService;
        $this->userService = $userService; // インジェクト
    }

    /**
     * 下書きを保存する
     * 新しい LedgerDiff (Content有り) を作成し、Ledger も更新する
     *
     * @param int|null $ledgerId 既存レコードID (新規なら null)
     * @param int $ledgerDefineId
     * @param array $content フォームの入力内容
     * @param array $contentAttached 添付ファイルの検索用インデックス
     * @param int $modifierId 操作者ID
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     * @throws Throwable
     */
    public function saveDraft(
        ?int  $ledgerId,
        int   $ledgerDefineId,
        array $content,
        array $contentAttached,
        int   $modifierId
    ): array
    {
        // ToDo: 権限チェック (modifierId が下書き保存できるか？)

        return DB::transaction(function () use ($ledgerId, $ledgerDefineId, $content, $contentAttached, $modifierId) {
            $ledger = null;
            $isNewLedger = false;

            if ($ledgerId) {
                $ledger = Ledger::findOrFail($ledgerId);
                // 承認済みなら編集できない
                if ($ledger->isLocked()) {
                    throw new Exception("Cannot save draft for an approved record.");
                }
                $currentVersion = $ledger->version + 1; // <<<--- 更新時はインクリメント
            } else {
                $isNewLedger = true;
                $currentVersion = 1;
                $ledger = Ledger::create([
                    'ledger_define_id' => $ledgerDefineId,
                    'creator_id' => $modifierId,
                    'modifier_id' => $modifierId,
                    'status' => WorkflowStatus::DRAFT,
                    'content' => $content, // 新規作成時から content を保存
                    'content_attached' => $contentAttached ?? [],
                    'version' => $currentVersion,
                ]);
                $ledgerId = $ledger->id;
            }
            $columnDefine = $ledger->define->column_define;

            // LedgerDiff (データスナップショット) を作成
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => $content,
                'column_define' => $columnDefine,
                'ledger_define_id' => $ledgerDefineId,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // この Diff 作成時のステータス
                'version' => $currentVersion,
                // 他のワークフローカラムは NULL
                'inspector_id' => null, 'approver_id' => null, 'requested_at' => null,
                'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger を更新 (content, modifier, status 等)
//            dd($isNewLedger);
            if ($isNewLedger) {
                // 新規作成時は latest_diff_id をセット
                $result = $ledger->update(['latest_diff_id' => $ledgerDiff->id]);
            } else {
                $result = $ledger->update([
                    'id' => $ledger->id,
                    'content' => $content,
                    'content_attached' => $contentAttached ?? [],
                    'status' => WorkflowStatus::DRAFT, // 念のため DRAFT に
                    'modifier_id' => $modifierId,
                    'version' => $currentVersion,
                    'latest_diff_id' => $ledgerDiff->id, // 最新Diff ID を更新
                ]);
            }

            // Activity Log 記録 (ステップ4)
            /* activity()->performedOn($ledger)->causedBy(User::find($modifierId))->log('draft_saved'); */
            Log::info("Draft saved for Ledger ID: {$ledgerId}, Diff ID: {$ledgerDiff->id}");

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $ledgerDiff];
        });
    }

    /**
     * 点検依頼を処理する
     * 新しい LedgerDiff (Content無し) を作成し、Ledger のステータス等を更新
     *
     * @param int $ledgerId 台帳レコードID
     * @param int $requesterId 点検依頼を行った User ID
     * @param int $inspectorId 次の担当者 User ID
     * @return Ledger 更新後の Ledger
     * @throws Throwable
     */
    public function requestInspection(int $ledgerId, int $requesterId, int $inspectorId): Ledger
    {
        // ToDo: 権限チェック (requesterId が点検依頼できるか？)
        $ledger = null; // 先に宣言
        $ledgerDiff = null; // 先に宣言

        DB::transaction(function () use ($ledgerId, $requesterId, $inspectorId, &$ledger, &$ledgerDiff) {
            $ledger = Ledger::findOrFail($ledgerId);
            if ($ledger->status !== WorkflowStatus::DRAFT) {
                throw new Exception("Inspection can only be requested from Draft status.");
            }
            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $requesterId,
                'status' => WorkflowStatus::PENDING_INSPECTION, // このアクション時点のステータス
                'version' => $ledgerVersion,
                'inspector_id' => $inspectorId, // 次の担当者
                'approver_id' => null,
                'requested_at' => now(),
                'inspected_at' => null, 'approved_at' => null, 'returned_at' => null, 'comments' => null,
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger のステータスと担当者、最新Diff ID を更新
            $ledger->update([
                'status' => WorkflowStatus::PENDING_INSPECTION,
                'modifier_id' => $requesterId,
                'latest_diff_id' => $ledgerDiff->id, // 最新の Diff ID
            ]);

            // カウンター更新 (点検者+)
            $this->incrementPendingTaskCount($inspectorId, 'inspection');
            // ToDo: イベント発行
            // event(new InspectionRequested($ledgerDiff));
        });

        // --- 通知処理 (トランザクション外) ---
        if ($ledgerDiff && $inspectorId) {
            $recipient = User::find($inspectorId);
            $notificationType = NotificationType::where('name', 'inspection_requested')->first();
            $folder = $ledger->define?->folder; // Folder を取得

            if ($recipient && $notificationType) {
                $this->notificationService->sendWorkflowNotification(
                    $recipient,
                    $notificationType,
                    $ledgerDiff,
                    null, // comment
                    $folder
                );
            }
        }
        // --- ここまで ---

        // ToDo: Activity Log 記録 (ステップ4)
        Log::info("Inspection requested for Ledger ID: {$ledgerId}. Inspector: {$inspectorId}. Diff ID: {$ledgerDiff->id}");

        return $ledger->refresh();
    }

    /**
     * 点検完了・承認申請を処理する
     * 新しい LedgerDiff (Content無し) を作成し、Ledger のステータス等を更新
     *
     * @param int $ledgerId
     * @param int $approverId 次の承認者 ID
     * @param int $inspectorId 点検操作を行った User ID
     * @return Ledger 更新後の Ledger
     * @throws Throwable
     */
    public function requestApproval(int $ledgerId, int $approverId, int $inspectorId, ?string $comments): Ledger
    {
        // ToDo: 権限チェック (inspectorId が点検を完了できるか？)
        $ledger = null;
        $ledgerDiff = null; // 作成する Diff を保持
        $applicant = null; // 申請者

        DB::transaction(function () use ($ledgerId, $approverId, $inspectorId, $comments, &$ledger, &$ledgerDiff, &$applicant) {
            $ledger = Ledger::findOrFail($ledgerId);
            $applicant = $ledger->creator; // 申請者を取得
            $ledgerDiff = $ledger->latestDiff()->first();
            if ($ledger->status !== WorkflowStatus::PENDING_INSPECTION || $ledgerDiff->inspector_id !== $inspectorId) {
                throw new Exception("User not authorized or invalid status for requesting approval.");
            }
            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $inspectorId,
                'status' => WorkflowStatus::PENDING_APPROVAL, // このアクション時点のステータス
                'version' => $ledgerVersion,
                'inspector_id' => $inspectorId, // 点検完了者
                'approver_id' => $approverId, // 次の承認者
                'requested_at' => $ledgerDiff->requested_at,
                'inspected_at' => now(), // 点検完了日時
                'comments' => $comments,
                'approved_at' => null, 'returned_at' => null,
            ];
            $ledgerDiff = LedgerDiff::create($diffData);


            // Ledger のステータスと担当者、最新Diff ID を更新
            $ledger->update([
                'status' => WorkflowStatus::PENDING_APPROVAL,
                'modifier_id' => $inspectorId,
                'latest_diff_id' => $ledgerDiff->id,
            ]);

            // カウンター更新 (点検者-, 承認者+)
            $this->decrementPendingTaskCount($inspectorId, 'inspection');
            $this->incrementPendingTaskCount($approverId, 'approval');
            // ToDo: イベント発行
            // event(new ApprovalRequested($ledgerDiff));
        });
        // --- 通知処理 ---
        // 承認依頼通知 (担当者向け - オプション)
        if ($ledgerDiff && $approverId) {
            $recipient = User::find($approverId);
            $notificationType = NotificationType::where('name', 'approval_requested')->first();
            $folder = $ledger->define?->folder;
            if ($recipient && $notificationType) {
                $this->notificationService->sendWorkflowNotification($recipient, $notificationType, $ledgerDiff, $comments, $folder);
            }
        }
        // 点検完了通知 (申請者向け - オプション)
        if ($applicant && $ledgerDiff) {
            $notificationType = NotificationType::where('name', 'inspection_completed')->first();
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $ledgerDiff, $comments, $folder);
            }
        }
        // --- ここまで ---

        // ToDo: Activity Log 記録 (ステップ4)
        Log::info("Inspection completed, approval requested for Ledger ID: {$ledgerId}. Approver: {$approverId}. Diff ID: {$ledgerDiff->id}");

        return $ledger->refresh();

    }

    /**
     * 承認処理を行う
     * 新しい LedgerDiff (Content無し) を作成し、Ledger を承認済みに更新し内容を反映
     *
     * @param int $ledgerId
     * @param int $approverId
     * @return Ledger 更新後の Ledger
     * @throws Throwable
     */
    public function approve(int $ledgerId, int $approverId): Ledger
    {
        // ToDo: 権限チェック (approverId が承認できるか？)
        $ledger = null;
        $ledgerDiff = null; // 作成する Diff を保持
        $applicant = null;

        DB::transaction(function () use ($ledgerId, $approverId, &$ledger, &$ledgerDiff, &$applicant) {
            $ledger = Ledger::findOrFail($ledgerId);
            $applicant = $ledger->creator;
            $latestDiff = $ledger->latestDiff()->first();

            if (!$latestDiff) {
                Log::error("Could not find LedgerDiff with content for Ledger ID: {$ledgerId} during approval.");
                throw new Exception("Cannot approve without content data.");
            }
            if ($ledger->status !== WorkflowStatus::PENDING_APPROVAL || $latestDiff->approver_id !== $approverId) {
                throw new Exception("User not authorized or invalid status for approving.");
            }
            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $approverId,
                'status' => WorkflowStatus::APPROVED, // このアクション時点のステータス
                'version' => $ledgerVersion, // <<<--- Ledger の version を記録
                'inspector_id' => $latestDiff->inspector_id, // 前の担当者
                'approver_id' => $approverId, // 承認者
                'requested_at' => $latestDiff->requested_at,
                'inspected_at' => $latestDiff->inspected_at,
                'approved_at' => now(), // 承認日時
                'returned_at' => null, 'comments' => null,
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger を更新 (最新の Diff 内容を反映)
            $ledger->update([
                'status' => WorkflowStatus::APPROVED,
                'modifier_id' => $approverId,
                'version' => $ledger->version + 1, // バージョンアップ
                'latest_diff_id' => $ledgerDiff->id, // ステータスのみのDiffも最新とする
                'comments' => null, // クリア
            ]);

            // カウンター更新 (承認者-)
            $this->decrementPendingTaskCount($approverId, 'approval');
            // ToDo: イベント発行
            // event(new ApprovalCompleted($ledgerDiff));
        });
        // --- 通知処理 ---
        if ($applicant && $ledgerDiff) {
            $notificationType = NotificationType::where('name', 'approved')->first();
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $ledgerDiff, null, $folder);
            }
        }
        // --- ここまで ---
        // ToDo: Activity Log 記録 (ステップ4)
        Log::info("Ledger approved for Ledger ID: {$ledgerId}. Diff ID: {$ledgerDiff->id}");

        return $ledger->refresh();

    }

    /**
     * ステータスを DRAFT に戻す (担当者が「作成中に戻す」ボタンを押した時)
     * 新しい LedgerDiff (Content無し) を作成し、Ledger のステータス等を更新
     *
     * @param int $ledgerId
     * @param int $modifierId 操作者ID (点検者 or 承認者)
     * @param string|null $comments 理由コメント
     * @return Ledger 更新後の Ledger
     * @throws Throwable
     */
    public function returnToDraft(int $ledgerId, int $modifierId, ?string $comments): Ledger
    {
        // ToDo: 権限チェック (modifierId が点検者/承認者か？)
        $ledger = null;
        $ledgerDiff = null; // 作成する Diff を保持
        $applicant = null;

        DB::transaction(callback: function () use ($ledgerId, $modifierId, $comments, &$ledger, &$ledgerDiff, &$applicant) {
            $ledger = Ledger::findOrFail($ledgerId);
            $applicant = $ledger->creator;
            $currentStatus = $ledger->status;
            $latestDiff = $ledger->latestDiff()->first();
            $handlerId = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? $latestDiff?->inspector_id : $latestDiff?->approver_id; // <<<--- 最新Diffから担当者取得

            if (in_array($ledger->status, [WorkflowStatus::DRAFT, WorkflowStatus::APPROVED])) {
                throw new Exception("Invalid status for returning to draft.");
            }
            // ToDo: さらに $modifierId が $latestDiff->inspector_id または $latestDiff->approver_id と一致するかチェック

            $ledgerVersion = $ledger->version; // 現在の Ledger バージョンを取得

            // LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => '',
                'column_define' => '',
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // このアクション時点のステータス
                'version' => $ledgerVersion, // <<<--- Ledger の version を記録
                'comments' => $comments,
                'returned_at' => now(), // 戻された日時
                'inspector_id' => $latestDiff->inspector_id, // 戻す直前の担当者
                'approver_id' => $latestDiff->approver_id,  // 戻す直前の担当者
                'requested_at' => $latestDiff->requested_at,
                'inspected_at' => self::getInspectedAtCarbonDate($latestDiff->inspected_at),
                'approved_at' => null, // クリア
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger を更新
            $ledger->update([
                'status' => WorkflowStatus::DRAFT,
                'returned_at' => now(), // Ledgerにも記録
                'modifier_id' => $modifierId,
                'latest_diff_id' => $ledgerDiff->id,
            ]);

            // カウンター調整 (担当者-)
            if ($handlerId && in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                $this->decrementPendingTaskCount($handlerId, $taskType);
            }

            // ToDo: イベント発行
            // event(new StatusReturnedToDraft($ledgerDiff, $comments));
        });
        // --- 通知処理 ---
        if ($applicant && $ledgerDiff) {
/*            if (!($applicant instanceof User)) {

                dd($applicant, $ledgerDiff);
            }*/
            $notificationType = NotificationType::where('name', 'status_returned_to_draft')->first();
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                // コメントも渡す
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $ledgerDiff, $comments, $folder);
            }
        }
        // --- ここまで ---
        // ToDo: Activity Log 記録 (ステップ4)
        Log::info("Ledger ID: {$ledgerId} returned to DRAFT by User ID: {$modifierId}. Diff ID: {$ledgerDiff->id}");

        return $ledger->refresh();

    }

    /**
     * 編集による DRAFT 戻し処理 (編集画面での保存時)
     * 新しい LedgerDiff (Content有り) を作成し、Ledger も更新。
     *
     * @param Ledger $ledger
     * @param array $newContent
     * @param array $newContentAttached
     * @param int $modifierId
     * @param string|null $comments
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     * @throws Throwable
     */
    public function saveEditedRecord(
        Ledger  $ledger,
        array   $newContent,
                $newContentAttached,
        int     $modifierId,
        ?string $comments
    ): array
    {
        if ($ledger->isLocked()) {
            throw new Exception("Cannot modify an approved record.");
        }
        // ToDo: modifierId が編集権限を持っているかチェック

        $newLedgerDiff = null; // 先に宣言

        DB::transaction(function () use ($ledger, $newContent, $newContentAttached, $modifierId, $comments, &$newLedgerDiff) {
            $currentStatus = $ledger->status; // 戻す前のステータス
            $latestDiff = $ledger->latestDiff()->first();
            $handlerId = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? $latestDiff->inspector_id : $latestDiff->approver_id;
            $ledgerVersion = $ledger->version + 1; // 現在の Ledger バージョン+1を取得

            // 1. 新しい LedgerDiff を作成 (Content有り)
            $diffData = [
                'ledger_id' => $ledger->id,
                'content' => $newContent ?? [], // 編集後の内容
                'column_define' => $ledger->define->column_define, // 編集時の定義
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // Diff ステータス
                'version' => $ledgerVersion, // <<<--- Ledger の version を記録
                'comments' => $comments, // 編集理由
                // 他のWFカラムはクリア
                'inspector_id' => $latestDiff->inspector_id ?? null,
                'approver_id' => $latestDiff->approver_id ?? null,
                'requested_at' => $latestDiff->requested_at ?? null,
                'inspected_at' => null,
                'approved_at' => null,
                'returned_at' => now(), // 編集により戻された日時
            ];
            $newLedgerDiff = LedgerDiff::create($diffData);

            // 2. Ledger を更新
            $ledger->update([
                'content' => $newContent ?? [], // 最新内容を反映
                'content_attached' => $newContentAttached ?? [],
                'status' => WorkflowStatus::DRAFT,
                'modifier_id' => $modifierId,
                'latest_diff_id' => $newLedgerDiff->id, // 最新Diff ID 更新
                'version' => $ledgerVersion,
            ]);


            // 4. ToDo: カウンター調整
            if ($handlerId && in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                $this->decrementPendingTaskCount($handlerId, $taskType);
            }

            // 5. ToDo: イベント発行
            // event(new StatusReturnedToDraft($ledger, $comments));

        });

        // --- 通知処理 ---
        $applicant = $ledger->creator;
        if ($applicant && $newLedgerDiff) {
            $notificationType = NotificationType::where('name', 'status_returned_to_draft')->first(); // 同じ通知タイプを使う？
            $folder = $ledger->define?->folder;
            if ($notificationType) {
                $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $newLedgerDiff, $comments, $folder);
            }
        }
        // --- ここまで ---

        // 3. ToDo: Activity Log 記録 (ステップ4)
        Log::info("Ledger ID: {$ledger->id} edited and returned to DRAFT by User ID: {$modifierId}. Diff ID: {$newLedgerDiff->id}");
        return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $newLedgerDiff];
    }


    // --- カウンター操作メソッド (実装) ---

    /**
     * 指定されたユーザーの未処理タスクカウンターをインクリメントする
     * @param int $userId 対象ユーザーID
     * @param string $type 'inspection' または 'approval'
     */
    protected function incrementPendingTaskCount(int $userId, string $type = 'approval'): void
    {
        try {
            $column = ($type === 'inspection') ? 'pending_inspection_count' : 'pending_approval_count';
            // DB::table('users')->where('id', $userId)->increment($column); // Query Builder
            User::where('id', $userId)->increment($column); // Eloquent
            Log::info("Incremented pending {$type} count for user: {$userId}");
        } catch (\Exception $e) {
            Log::error("Failed to increment pending {$type} count for user {$userId}: " . $e->getMessage());
            // ここで例外を再スローするかどうかは要件による
        }
    }

    /**
     * 指定されたユーザーの未処理タスクカウンターをデクリメントする
     * @param int $userId 対象ユーザーID
     * @param string $type 'inspection' または 'approval'
     */
    protected function decrementPendingTaskCount(int $userId, string $type): void
    {
        try {
            $column = ($type === 'inspection') ? 'pending_inspection_count' : 'pending_approval_count';
            // マイナスにならないように where でガード
            // DB::table('users')->where('id', $userId)->where($column, '>', 0)->decrement($column);
            User::where('id', $userId)->where($column, '>', 0)->decrement($column); // Eloquent
            Log::info("Decremented pending {$type} count for user: {$userId}");
        } catch (\Exception $e) {
            Log::error("Failed to decrement pending {$type} count for user {$userId}: " . $e->getMessage());
        }
    }

    /**
     * @param $targetDate string|Carbon|null
     * @return \Carbon\Carbon|null
     */
    static function getInspectedAtCarbonDate($targetDate): ?\Carbon\Carbon
    {
        return \Carbon\Carbon::hasFormat($targetDate, 'Y-m-d H:i:s') && $targetDate !== '0000-00-00 00:00:00'
            ? \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $targetDate)
            : null;
    }

    /**
     * 特定の台帳定義と役割タイプにおいて、過去に頻繁に担当したユーザーを取得する
     *
     * @param int $ledgerDefineId
     * @param string $roleType 'inspector' または 'approver'
     * @param int $limit 取得する最大件数
     * @param string $searchQuery ユーザー名での検索クエリ (オプション)
     * @return array [['id' => userId, 'name' => userName, 'count' => N], ...]
     */
    public function getFrequentAssignees(int $ledgerDefineId, string $roleType, int $limit, string $searchQuery = ''): array
    {
        $column = ($roleType === 'inspector') ? 'inspector_id' : 'approver_id';

        $query = LedgerDiff::select("ledger_diffs.{$column} as user_id", 'users.name as user_name', DB::raw('count(*) as count'))
            ->join('users', 'users.id', '=', "ledger_diffs.{$column}")
            ->where('ledger_diffs.ledger_define_id', $ledgerDefineId) // テーブル名を明確化
            ->whereNotNull("ledger_diffs.{$column}")
            ->when($searchQuery, function ($q) use ($searchQuery) {
                $q->where('users.name', 'like', "%{$searchQuery}%");
            })
            ->groupBy('user_id', 'users.name')
            ->orderByDesc('count')
            ->limit($limit);

        return $query->get()
            ->map(fn($diff) => ['id' => $diff->user_id, 'name' => $diff->user_name ?? __('ledger.unknown_user'), 'count' => $diff->count])
            ->filter(fn($item) => $item['id'] !== null)
            ->toArray();
    }

    /**
     * ワークフロータスクを引き継ぐ
     *
     * @param Ledger $ledger 引き継ぎ対象のLedger
     * @param User $claimer 引き継ぎを行うユーザー (新しい担当者)
     * @param string|null $comments 引き継ぎコメント
     * @return Ledger 更新後のLedger
     * @throws \Throwable
     */
    public function claimTask(Ledger $ledger, User $claimer, ?string $comments): Ledger
    {
        // 1. 基本的なバリデーション
        if (!$ledger->status->isWorkflowPending()) { // isWorkflowPending() は Enum に定義されている想定
            throw new \Exception(__('ledger.errors.cannot_claim_not_pending_task'));
        }
        if ($ledger->creator_id === $claimer->id) {
            throw new \Exception(__('ledger.errors.applicant_cannot_claim'));
        }

        $latestDiff = $ledger->latestDiff()->first();
        if (!$latestDiff) {
            throw new \Exception(__('ledger.errors.latest_diff_not_found'));
        }

        // 2. 現在の担当者情報を取得 (元の担当者)
        $originalAssignee = null;
        $currentTaskRoleType = ''; // 元の担当者の役割タイプ (inspector or approver)

        if ($ledger->status === WorkflowStatus::PENDING_INSPECTION && $latestDiff->inspector_id) {
            $originalAssignee = $latestDiff->inspector; // リレーションで User を取得
            $currentTaskRoleType = 'inspection';
        } elseif ($ledger->status === WorkflowStatus::PENDING_APPROVAL && $latestDiff->approver_id) {
            $originalAssignee = $latestDiff->approver; // リレーションで User を取得
            $currentTaskRoleType = 'approval';
        }

        // 既に自分が担当者ならエラー
        if ($originalAssignee && $originalAssignee->id === $claimer->id) {
            throw new \Exception(__('ledger.errors.already_assignee'));
        }

        // 3. 引き継ぎ者の権限チェック
        $requiredPermission = ($ledger->status === WorkflowStatus::PENDING_INSPECTION)
            ? FolderPermissionType::INSPECT
            : FolderPermissionType::APPROVE;
        if (!$ledger->define?->folder || !$this->userService->hasFolderPermission($claimer, $ledger->define->folder, $requiredPermission)) {
            throw new \Exception(__('ledger.errors.no_permission_to_claim'));
        }


        return DB::transaction(function () use ($ledger, $claimer, $comments, $latestDiff, $originalAssignee, $currentTaskRoleType) {
            $now = now();
            // ステータスは引き継ぎなので変更しない
            $newStatus = $ledger->status;

            // 4. 新しい LedgerDiff を作成
            $newDiffData = [
                'ledger_id' => $ledger->id,
                'content' => '', // 引き継ぎ時は内容は変更しない
                'column_define' => '', // 同上
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id, // 元の申請者は維持
                'modifier_id' => $claimer->id, // 操作者は引き継ぎ者
                'status' => $newStatus, // ステータスは維持
                'version' => $ledger->version, // バージョンも維持
                'comments' => $comments, // 引き継ぎコメント
                'requested_at' => $latestDiff->requested_at, // 元の依頼日時は維持
                'inspected_at' => ($newStatus === WorkflowStatus::PENDING_APPROVAL) ? $latestDiff->inspected_at : null, // 点検完了日時は維持 (承認待ちの場合)
                'approved_at' => null, // 承認日時はクリア
                'returned_at' => null, // 差し戻しではない
                // 新しい担当者を設定
                'inspector_id' => ($newStatus === WorkflowStatus::PENDING_INSPECTION) ? $claimer->id : $latestDiff->inspector_id,
                'approver_id' => ($newStatus === WorkflowStatus::PENDING_APPROVAL) ? $claimer->id : $latestDiff->approver_id,
            ];
            $newLedgerDiff = LedgerDiff::create($newDiffData);

            // 5. Ledger の latest_diff_id と modifier_id を更新
            $ledger->update([
                'latest_diff_id' => $newLedgerDiff->id,
                'modifier_id' => $claimer->id, // Ledger の最終更新者も引き継ぎ者に
            ]);

            // 6. カウンター調整
            if ($originalAssignee && !empty($currentTaskRoleType)) { // 元の担当者がいて、役割タイプが特定できればデクリメント
                $this->decrementPendingTaskCount($originalAssignee->id, $currentTaskRoleType);
            }
            // 新しい担当者のカウンターをインクリメント (役割タイプはステータスから判断)
            $newTaskType = ($newStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
            $this->incrementPendingTaskCount($claimer->id, $newTaskType);

            // 7. 通知処理
            $notificationType = NotificationType::where('name', 'task_claimed')->first();
            if ($notificationType) {
                $folder = $ledger->define?->folder;
                $recipients = collect();

                // 申請者に通知 (引き継ぎ者と異なる場合)
                if ($ledger->creator_id !== $claimer->id && $ledger->creator) {
                    $recipients->push($ledger->creator);
                }
                // 元の担当者に通知 (存在し、引き継ぎ者と異なる場合)
                if ($originalAssignee && $originalAssignee->id !== $claimer->id) {
                    $recipients->push($originalAssignee);
                }
                // 新しい担当者(引き継ぎ者本人)にも通知が必要か？ -> 通常は操作者なので不要だが、確認のため通知しても良い
                $recipients->push($claimer); // 自分にも通知を送る場合

                foreach ($recipients->unique('id') as $recipientUser) {
                    if ($recipientUser) { // null チェック
                        $this->notificationService->sendWorkflowNotification(
                            $recipientUser,
                            $notificationType,
                            $ledger, // subject は Ledger
                            $comments,
                            $folder,
                            $originalAssignee // originalAssignee を渡す
                        );
                    }
                }
            }
            return $ledger->refresh();
        });
    }
}
