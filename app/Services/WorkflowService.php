<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

// Log ファサードを use

class WorkflowService
{
    /**
     * 下書きを保存する
     * Ledger と LedgerDiff を作成/更新し、Ledger に最新データを反映する
     *
     * @param  array|Collection  $columnDefine
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     *
     * @throws Throwable
     */
    public function saveDraft(
        ?int $ledgerId,
        int $ledgerDefineId,
        array $content,
        $columnDefine,
        int $modifierId
    ): array {
        return DB::transaction(function () use (&$ledgerId, $ledgerDefineId, $content, $columnDefine, $modifierId) {
            $ledger = null;
            $currentVersion = 0;

            if ($ledgerId) {
                $ledger = Ledger::findOrFail($ledgerId);
                $currentVersion = $ledger->version;
            } else {
                $ledger = Ledger::create([
                    'ledger_define_id' => $ledgerDefineId,
                    'creator_id' => $modifierId,
                    'modifier_id' => $modifierId,
                    'status' => WorkflowStatus::DRAFT,
                    'content' => $content, // <<<--- 新規作成時もコンテンツを保存
                    'content_attached' => $content['content_attached'] ?? [], // <<<--- 添付情報も保存
                    'version' => 1,
                ]);
                $ledgerId = $ledger->id;
                $currentVersion = 1; // 新規作成時はバージョン1
            }

            // LedgerDiff のデータ
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => $content,
                'column_define' => $columnDefine,
                'ledger_define_id' => $ledgerDefineId,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // Diff のステータスは操作時点のもの
                'inspector_id' => null,
                'approver_id' => null,
                'requested_at' => null,
                'inspected_at' => null,
                'approved_at' => null,
                'returned_at' => null,
                'comments' => null,
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger を更新 (ステータス、コンテンツ、バージョンなど)
            if (! $ledger->wasRecentlyCreated) { // 既存レコードの場合のみ更新
                $ledger->update([
                    'content' => $content,
                    'content_attached' => $content['content_attached'] ?? [],
                    'status' => WorkflowStatus::DRAFT,
                    'modifier_id' => $modifierId,
                    'version' => $currentVersion,
                ]);
            } else {
                // 新規作成時は create で既にセットされている
            }

            Log::info("Draft saved for Ledger ID: {$ledgerId}, Diff ID: {$ledgerDiff->id}");

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $ledgerDiff];
        });
    }

    /**
     * 点検依頼を処理する
     * Ledger のステータスを更新し、新しい LedgerDiff を作成する。
     * 前提：対応する Ledger レコードは既に存在する (saveDraft で作成済み)。
     *
     * @param  int  $ledgerId  対象の Ledger ID (必須)
     * @param  array  $currentContent  現在のフォームの内容 (最新のスナップショット用)
     * @param  array|\Illuminate\Support\Collection  $currentColumnDefine  現在の台帳定義
     * @param  int  $requesterId  申請者ID
     * @param  int  $inspectorId  点検者ID
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     *
     * @throws Throwable
     */
    public function requestInspection(
        int $ledgerId, // <<<--- Nullable を解除し必須に
        array $currentContent,
        $currentColumnDefine,
        int $requesterId,
        int $inspectorId
    ): array {
        // ToDo: 権限チェック (申請者がこの操作を行えるか)

        return DB::transaction(function () use ($ledgerId, $requesterId, $inspectorId) {
            // 1. 対象の Ledger を取得 (findOrFail で存在確認)
            $ledger = Ledger::findOrFail($ledgerId);

            // 2. 現在のステータスチェック (DRAFT からのみ申請可能とする)
            if ($ledger->status !== WorkflowStatus::DRAFT) {
                Log::warning("Inspection request for non-draft ledger. Ledger ID: {$ledgerId}, Status: {$ledger->status->value}");
                throw new \Exception('Inspection can only be requested from Draft status.');
            }

            // 3. 新しい LedgerDiff を作成 (点検依頼時点のスナップショット)
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => [],
                //                'content_attached' => $currentContent['content_attached'] ?? [], // <<<--- 最新の attached を保存
                'column_define' => [],
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $requesterId, // 今回の操作者
                'status' => WorkflowStatus::PENDING_INSPECTION, // Diff のステータス
                'inspector_id' => $inspectorId,
                'approver_id' => null,
                'requested_at' => now(), // 申請日時
                // inspected_at, approved_at, returned_at, comments は null
            ];
            $ledgerDiff = LedgerDiff::create($diffData);

            // 4. Ledger のステータスと modifier を更新
            $ledger->update([
                'status' => WorkflowStatus::PENDING_INSPECTION,
                'modifier_id' => $requesterId,
                // content, version はこの時点では更新しない (承認時に更新)
            ]);

            // 5. ToDo: 点検者の未処理カウンターをインクリメント
            $this->incrementPendingTaskCount($inspectorId, 'inspection'); // type を指定

            // 6. ToDo: 関連イベントを発行 (通知用)
            // event(new InspectionRequested($ledgerDiff));

            Log::info("Inspection requested for Ledger ID: {$ledgerId}, Diff ID: {$ledgerDiff->id}");

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $ledgerDiff];
        });
    }

    /**
     * 点検完了・承認申請を処理する
     * Ledger のステータスを更新し、Activity Log に記録する (LedgerDiff は更新しない)
     *
     * @param  LedgerDiff  $latestDiff  最新の LedgerDiff (承認対象のデータを含む)
     * @return Ledger 更新後の Ledger オブジェクト
     *
     * @throws Throwable
     */
    public function requestApproval(LedgerDiff $latestDiff, int $approverId, int $inspectorId): Ledger
    {
        // 点検完了時の LedgerDiff は最新のはず
        $ledger = $latestDiff->ledger()->firstOrFail();

        // 権限チェック
        if ($ledger->status !== WorkflowStatus::PENDING_INSPECTION) {
            throw new Exception('Invalid status for requesting approval.');
        }
        // ここでは LedgerDiff の inspector_id を見るのではなく、最新のプロセス履歴(Activity Log)で担当者を確認するのがより正確
        // $latestLog = Activity::where('subject_type', LedgerDiff::class)->where('subject_id', $latestDiff->id)->where('event', 'inspection_requested')->latest()->first();
        // if(!$latestLog || $latestLog->properties->get('next_inspector_id') !== $inspectorId) { ... }
        // 今回は簡易的に LedgerDiff の inspector_id を使う
        if ($latestDiff->inspector_id !== $inspectorId) {
            throw new Exception('User not authorized for this inspection.');
        }

        return DB::transaction(function () use ($ledger, $latestDiff, $approverId, $inspectorId) {
            // LedgerDiff のデータ (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledger->id,
                'content' => [], // <<<--- NULL
                //                'content_attached' => null, // <<<--- NULL
                'column_define' => [], // <<<--- NULL
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $inspectorId, // 今回の操作者
                'status' => WorkflowStatus::PENDING_APPROVAL, // Diff のステータス
                'inspector_id' => $inspectorId, // 完了した点検者
                'approver_id' => $approverId, // 次の承認者
                'requested_at' => $latestDiff->requested_at, // 前の依頼日時を引き継ぐ？ or null?
                'inspected_at' => now(), // 点検完了日時
                'approved_at' => null,
                'returned_at' => null,
                'comments' => null, // 点検完了コメントは不要？
            ];
            $newLedgerDiff = LedgerDiff::create($diffData);

            // Ledger のステータスを更新
            $ledger->update([
                'status' => WorkflowStatus::PENDING_APPROVAL,
                'modifier_id' => $inspectorId, // 今回の操作者
            ]);

            Log::info("Inspection completed, approval requested for Ledger ID: {$ledger->id}. Diff ID: {$newLedgerDiff->id}");

            // Activity Log に記録 (ステップ4で実装)
            /*
            activity()
               ->performedOn($latestDiff) // ログの対象は最新の Diff
               ->causedBy(User::find($inspectorId))
               ->withProperties(['next_approver_id' => $approverId, 'comments' => '点検完了']) // 次の担当者情報など
               ->log('inspection_completed');
            */
            Log::info("Inspection completed, approval requested for Ledger ID: {$ledger->id}. Next approver: {$approverId}");

            // ToDo: 点検者のカウンターをデクリメント
            $this->decrementPendingTaskCount($inspectorId, 'inspection');
            // ToDo: 承認者のカウンターをインクリメント
            $this->incrementPendingTaskCount($approverId, 'approval'); // type を変える

            // ToDo: 関連イベントを発行 (通知用)
            // event(new ApprovalRequested($latestDiff));

            return $ledger->refresh(); // 更新後の Ledger を返す
        });
    }

    /**
     * 承認処理を行う
     * Ledger のステータスを更新し、バージョンを上げる。Activity Log に記録。
     *
     * @param  LedgerDiff  $latestDiff  最新の LedgerDiff
     * @return Ledger 更新後の Ledger オブジェクト
     *
     * @throws Throwable
     */
    public function approve(LedgerDiff $latestDiff, int $approverId): Ledger
    {
        $ledger = $latestDiff->ledger()->firstOrFail();

        // 権限チェック
        if ($ledger->status !== WorkflowStatus::PENDING_APPROVAL) {
            throw new Exception('Invalid status for approving.');
        }
        // Activity Log で担当者確認 or LedgerDiff で確認 (今回は LedgerDiff)
        if ($latestDiff->approver_id !== $approverId) {
            throw new Exception('User not authorized for this approval.');
        }

        return DB::transaction(function () use ($ledger, $approverId) {
            // Ledger を更新
            $ledger->update([
                'status' => WorkflowStatus::APPROVED,
                'modifier_id' => $approverId,
                'version' => $ledger->version + 1, // バージョンインクリメント
                // content, content_attached は最新のはずなので更新不要
            ]);

            // Activity Log に記録 (ステップ4で実装)
            /*
            activity()
               ->performedOn($latestDiff)
               ->causedBy(User::find($approverId))
               ->log('approved');
            */
            Log::info("Ledger approved for Ledger ID: {$ledger->id}");

            // ToDo: 承認者のカウンターをデクリメント
            $this->decrementPendingTaskCount($approverId, 'approval');

            // ToDo: 関連イベントを発行 (通知用)
            // event(new ApprovalCompleted($latestDiff));

            return $ledger->refresh();
        });
    }

    /**
     * ステータスを DRAFT に戻す (差し戻し相当 or 編集中保存)
     * 新しい LedgerDiff を作成し、Ledger のステータス、内容、バージョン等を更新。Activity Log に記録。
     *
     * @param  Ledger  $ledger  対象の Ledger
     * @param  array  $newContent  編集後の内容
     * @param  int  $modifierId  操作を実行したユーザーID
     * @param  string|null  $comments  理由コメント (任意)
     * @param  string  $logEvent  Activity Log に記録するイベント名
     * @return array{ledger: Ledger, ledgerDiff: LedgerDiff}
     *
     * @throws Throwable
     */
    public function returnToDraft(
        Ledger $ledger,
        array $newContent,
        int $modifierId,
        ?string $comments,
        string $logEvent = 'returned_to_draft' // or 'edited_while_pending'
    ): array {
        // 権限チェック (編集権限があるか、$ledger->isLocked() でないか等)
        if ($ledger->isLocked()) {
            throw new Exception('Cannot modify an approved record.');
        }
        // ToDo: modifierId が編集権限を持っているかチェック

        return DB::transaction(function () use ($ledger, $modifierId, $comments, $logEvent) {

            $currentStatus = $ledger->status; // 戻す前のステータス

            // 1. 新しい LedgerDiff を作成 (content 等は NULL)
            $diffData = [
                'ledger_id' => $ledger->id,
                'content' => [],
                //                    'content_attached' => null, // <<<--- NULL
                'column_define' => [], // <<<--- NULL
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // Diff のステータス
                'comments' => $comments,
                'returned_at' => now(), // 戻された日時
                // 他のワークフローカラムはクリア
                'inspector_id' => null,
                'approver_id' => null,
                'requested_at' => null,
                'inspected_at' => null,
                'approved_at' => null,
            ];
            $newLedgerDiff = LedgerDiff::create($diffData);

            // 2. Ledger を更新
            $ledger->update([
                'status' => WorkflowStatus::DRAFT,
                'modifier_id' => $modifierId,
                // content, version は変更しない
            ]);


            // 3. Activity Log に記録 (ステップ4で実装)
            /*
            activity()
               ->performedOn($newLedgerDiff) // 新しい Diff を対象に
               ->causedBy(User::find($modifierId))
               ->withProperties(['comments' => $comments, 'previous_status' => $currentStatus->value])
               ->log($logEvent);
            */
            Log::info("Ledger ID: {$ledger->id} returned to DRAFT by User ID: {$modifierId}. Diff ID: {$newLedgerDiff->id}. Event: {$logEvent}");

            // 4. ToDo: カウンター調整 (もし PENDING 状態から戻した場合)
            if (in_array($currentStatus, [WorkflowStatus::PENDING_INSPECTION, WorkflowStatus::PENDING_APPROVAL])) {
                $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                // 最新の Diff または Activity Log から担当者 ID を取得する必要がある
                $latestDiffBeforeReturn = LedgerDiff::where('ledger_id', $ledger->id)
                    ->where('id', '!=', $newLedgerDiff->id) // 今回作成したものは除く
                    ->latest('id')->first();
                $handlerId = ($taskType === 'inspection') ? $latestDiffBeforeReturn?->inspector_id : $latestDiffBeforeReturn?->approver_id;
                if ($handlerId) {
                    $this->decrementPendingTaskCount($handlerId, $taskType);
                }
            }

            // 5. ToDo: 関連イベント発行 (通知用)
            // event(new StatusReturnedToDraft($newLedgerDiff, $comments));

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $newLedgerDiff];
        });
    }

    /**
     * 編集による DRAFT 戻し処理
     * 新しい LedgerDiff を作成し、content 等を記録。Ledger も更新。
     */
    public function saveEditedRecord(
        Ledger $ledger,
        array $newContent,
               $columnDefine, // 編集時の ColumnDefine
        int $modifierId,
        ?string $comments
    ): array {
        // ... (権限チェック: isLocked でないか等) ...

        return DB::transaction(function () use ($ledger, $newContent, $columnDefine, $modifierId, $comments) {
            $currentStatus = $ledger->status; // 戻す前のステータス

            // 1. 新しい LedgerDiff を作成 (content 等を記録)
            $diffData = [
                'ledger_id' => $ledger->id,
                'content' => $newContent['content'] ?? [], // <<<--- 編集後の内容を記録
//                'content_attached' => $newContent['content_attached'] ?? [], // <<<--- 編集後の内容を記録
                'column_define' => $columnDefine, // <<<--- 編集時の定義を記録
                'ledger_define_id' => $ledger->ledger_define_id,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT, // Diff ステータスは DRAFT
                'comments' => $comments, // 編集理由
                // 他のワークフローカラムはクリア
                'inspector_id' => null,
                'approver_id' => null,
                'requested_at' => null,
                'inspected_at' => null,
                'approved_at' => null,
                'returned_at' => now(), // 編集により戻された日時
            ];
            $newLedgerDiff = LedgerDiff::create($diffData);

            // 2. Ledger を更新
            $ledger->update([
                'content' => $newContent['content'] ?? [], // Ledger にも最新内容を反映
                'content_attached' => $newContent['content_attached'] ?? [],
                'status' => WorkflowStatus::DRAFT,
                'modifier_id' => $modifierId,
                // バージョンは上げない
            ]);

            // ... (Activity Log 記録、カウンター調整、イベント発行) ...
            Log::info("Ledger ID: {$ledger->id} edited and returned to DRAFT by User ID: {$modifierId}. Diff ID: {$newLedgerDiff->id}");

            return ['ledger' => $ledger->refresh(), 'ledgerDiff' => $newLedgerDiff];
        });
    }

    /**
     * 未処理タスクカウンターをインクリメントする (仮実装)
     */
    protected function incrementPendingTaskCount(int $userId): void
    {
        // User モデルにカウンターカラムがある場合:
        // User::find($userId)?->increment('pending_inspection_count');
        // Cache を使う場合:
        // Cache::increment("user:{$userId}:pending_inspection_count");
        \Log::info("Increment pending inspection count for user: {$userId}"); // ログ出力
    }

    protected function decrementPendingTaskCount(int $userId, string $type): void
    {
        // 実際のカウンター更新処理
        Log::info("Decrement pending {$type} count for user: {$userId}");
    }
}
