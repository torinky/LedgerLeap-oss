<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Carbon\Carbon;
use Carbon\Traits\Date;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WorkflowService
{
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

        return DB::transaction(function () use ($ledgerId, $requesterId, $inspectorId) {
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
            // ToDo: Activity Log 記録 (ステップ4)
            Log::info("Inspection requested for Ledger ID: {$ledgerId}. Inspector: {$inspectorId}. Diff ID: {$ledgerDiff->id}");


            return $ledger->refresh();
        });
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
    public function requestApproval(int $ledgerId, int $approverId, int $inspectorId): Ledger
    {
        // ToDo: 権限チェック (inspectorId が点検を完了できるか？)

        return DB::transaction(function () use ($ledgerId, $approverId, $inspectorId) {
            $ledger = Ledger::findOrFail($ledgerId);
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
                'approved_at' => null, 'returned_at' => null, 'comments' => null,
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
            // ToDo: Activity Log 記録 (ステップ4)
            Log::info("Inspection completed, approval requested for Ledger ID: {$ledgerId}. Approver: {$approverId}. Diff ID: {$ledgerDiff->id}");

            return $ledger->refresh();
        });
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

        return DB::transaction(function () use ($ledgerId, $approverId) {
            $ledger = Ledger::findOrFail($ledgerId);
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
            // ToDo: Activity Log 記録 (ステップ4)
            Log::info("Ledger approved for Ledger ID: {$ledgerId}. Diff ID: {$ledgerDiff->id}");

            return $ledger->refresh();
        });
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

        return DB::transaction(callback: function () use ($ledgerId, $modifierId, $comments) {
            $ledger = Ledger::findOrFail($ledgerId);
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
            $lDiff = LedgerDiff::create($diffData);

            // Ledger を更新
            $ledger->update([
                'status' => WorkflowStatus::DRAFT,
                'returned_at' => now(), // Ledgerにも記録
                'modifier_id' => $modifierId,
                'latest_diff_id' => $lDiff->id,
            ]);

            // カウンター調整 (担当者-)
            if ($handlerId && in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                $this->decrementPendingTaskCount($handlerId, $taskType);
            }

            // ToDo: イベント発行
            // event(new StatusReturnedToDraft($ledgerDiff, $comments));
            // ToDo: Activity Log 記録 (ステップ4)
            Log::info("Ledger ID: {$ledgerId} returned to DRAFT by User ID: {$modifierId}. Diff ID: {$lDiff->id}");

            return $ledger->refresh();
        });
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

        return DB::transaction(function () use ($ledger, $newContent, $newContentAttached, $modifierId, $comments) {
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

            // 3. ToDo: Activity Log 記録 (ステップ4)
            Log::info("Ledger ID: {$ledger->id} edited and returned to DRAFT by User ID: {$modifierId}. Diff ID: {$newLedgerDiff->id}");

            // 4. ToDo: カウンター調整
            if ($handlerId && in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                $this->decrementPendingTaskCount($handlerId, $taskType);
            }

            // 5. ToDo: イベント発行
            // event(new StatusReturnedToDraft($ledger, $comments));

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $newLedgerDiff];
        });
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
}
