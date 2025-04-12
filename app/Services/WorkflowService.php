<?php

namespace App\Services;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

// Log ファサードを use

class WorkflowService
{
    /**
     * 点検依頼を処理し、LedgerDiff を作成/更新する
     *
     * @param int|null $ledgerId 既存レコードID (新規の場合は null、仮作成される)
     * @param int $ledgerDefineId
     * @param array $content 申請内容
     * @param array|Collection $columnDefine
     * @param int $requesterId 申請者ID
     * @param int $inspectorId 点検者ID // <<<--- 引数を追加
     * @return LedgerDiff 作成/更新された LedgerDiff レコード
     * @throws Throwable
     */
    public function requestInspection(
        ?int  $ledgerId, // 参照渡しに変更 (新規作成時にIDを返すため)
        int   $ledgerDefineId,
        array $content,
              $columnDefine,
        int   $requesterId,
        int   $inspectorId // <<<--- 引数を追加
    ): array
    {
        // ToDo: 権限チェック (申請者がこの操作を行えるか)

        return DB::transaction(function () use (&$ledgerId, $ledgerDefineId, $content, $columnDefine, $requesterId, $inspectorId) {
            $ledger = null;
            $isNewLedger = false;

            if ($ledgerId) {
                // 既存レコードIDが渡された場合
                $ledger = Ledger::findOrFail($ledgerId);
            } else {
                // 新規レコードの場合、まず Ledger を DRAFT で作成
                $isNewLedger = true;
                $ledger = Ledger::create([
                    'ledger_define_id' => $ledgerDefineId,
                    'creator_id' => $requesterId,
                    'modifier_id' => $requesterId,
                    'status' => WorkflowStatus::DRAFT, // 初期ステータスは DRAFT
                    'content' => [], // 初期コンテンツ
                    'content_attached' => [],
                    'version' => 1,
                ]);
                $ledgerId = $ledger->id; // 新しく採番された ID を参照元に返す
            }

            // LedgerDiff のデータ準備
            $diffData = [
                'ledger_id' => $ledgerId, // 確定した ledger_id
                'content' => $content,
                'column_define' => $columnDefine,
                'ledger_define_id' => $ledgerDefineId,
                // creator_id は Ledger 作成者を、modifier_id は今回の操作者を引き継ぐ
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $requesterId, // 今回の申請者
                'status' => WorkflowStatus::PENDING_INSPECTION, // ステータスを点検待ちに
                'inspector_id' => $inspectorId,
                'approver_id' => null,
                'requested_at' => now(), // 申請日時をセット
                'inspected_at' => null,
                'approved_at' => null,
                'returned_at' => null,
                'comments' => null,
            ];

            // LedgerDiff を作成
            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger のステータスも更新 (新規作成 or 既存更新に関わらず)
            $ledger->update(['status' => WorkflowStatus::PENDING_INSPECTION]);

            // ToDo: 点検者の未処理カウンターをインクリメント
            $this->incrementPendingTaskCount($inspectorId);

            // ToDo: 関連イベントを発行 (通知用)
            // event(new InspectionRequested($ledgerDiff));

            Log::info("Inspection requested for Ledger ID: {$ledgerId}, Diff ID: {$ledgerDiff->id}");

            return ['ledger' => $ledger, 'ledgerDiff' => $ledgerDiff];
        });
    }



    /**
     * 下書きを保存する (LedgerDiff のみ作成/更新)
     *
     * @param int|null $ledgerId
     * @param int $ledgerDefineId
     * @param array $content
     * @param $columnDefine
     * @param int $modifierId
     * @param Ledger|null $ledgerRecord
     * @return LedgerDiff
     * @throws Throwable
     */
    public function saveDraft(
        ?int    $ledgerId,
        int     $ledgerDefineId,
        array   $content,
                $columnDefine,
        int     $modifierId,
        ?Ledger $ledgerRecord = null
    ): array
    {
        return DB::transaction(function () use (&$ledgerId, $ledgerDefineId, $content, $columnDefine, $modifierId, $ledgerRecord) {
            $ledger = null;
            $isNewLedger = false;

            if ($ledgerId) {
                $ledger = Ledger::findOrFail($ledgerId);
            } elseif (!$ledgerRecord) { // 新規かつ仮レコードもない場合
                $isNewLedger = true;
                $ledger = Ledger::create([
                    'ledger_define_id' => $ledgerDefineId,
                    'creator_id' => $modifierId, // 下書き作成者が creator
                    'modifier_id' => $modifierId,
                    'status' => WorkflowStatus::DRAFT,
                    'content' => [],
                    'content_attached' => [],
                    'version' => 1,
                ]);
                $ledgerId = $ledger->id;
            } else {
                $ledger = $ledgerRecord; // mount で取得した仮レコード
            }

            // LedgerDiff のデータ
            $diffData = [
                'ledger_id' => $ledgerId,
                'content' => $content,
                'column_define' => $columnDefine,
                'ledger_define_id' => $ledgerDefineId,
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifierId,
                'status' => WorkflowStatus::DRAFT,
                // 他のワークフロー関連カラムは NULL またはデフォルト値
                'inspector_id' => null,
                'approver_id' => null,
                'requested_at' => null,
                'inspected_at' => null,
                'approved_at' => null,
                'returned_at' => null,
                'comments' => null,
            ];

            $ledgerDiff = LedgerDiff::create($diffData);

            // Ledger のステータスが DRAFT でない場合は DRAFT に更新
            if ($ledger->status !== WorkflowStatus::DRAFT) {
                $ledger->update(['status' => WorkflowStatus::DRAFT]);
            }

            Log::info("Draft saved for Ledger ID: {$ledgerId}, Diff ID: {$ledgerDiff->id}");

            return ['ledger' => $ledger, 'ledgerDiff' => $ledgerDiff];
        });
    }

    /**
     * 点検完了・承認申請を処理し、LedgerDiff のステータスを更新する
     *
     * @param LedgerDiff $ledgerDiff 更新対象の LedgerDiff オブジェクト
     * @param int $approverId 選択された承認者の User ID
     * @param int $inspectorId 点検操作を行った User ID
     * @return LedgerDiff 更新後の LedgerDiff オブジェクト
     * @throws Throwable
     */
    public function requestApproval(LedgerDiff $ledgerDiff, int $approverId, int $inspectorId): LedgerDiff
    {
        // ToDo: 権限チェック (inspectorId が本当にこの $ledgerDiff の点検者か？)
        if ($ledgerDiff->status !== WorkflowStatus::PENDING_INSPECTION) {
            // エラー処理またはログ記録
            Log::warning("Invalid status transition requested for LedgerDiff ID: {$ledgerDiff->id}");
            throw new Exception("Invalid status for requesting approval."); // 例
        }
        if ($ledgerDiff->inspector_id !== $inspectorId) {
            Log::warning("Unauthorized inspection completion attempt by User ID: {$inspectorId} for LedgerDiff ID: {$ledgerDiff->id}");
            throw new Exception("User not authorized for this inspection."); // 例
        }


        return DB::transaction(function () use ($ledgerDiff, $approverId, $inspectorId) {
            $ledgerDiff->update([
                'status' => WorkflowStatus::PENDING_APPROVAL,
                'approver_id' => $approverId,
                'inspector_id' => $inspectorId, // 点検者IDも記録 (誰が点検完了したか)
                'inspected_at' => now(), // 点検完了日時
                'modifier_id' => $inspectorId, // 今回の操作者
                // requested_at は変更しない
                // approved_at, returned_at, comments は null のまま
            ]);

            // ToDo: 点検者のカウンターをデクリメント
            $this->decrementPendingTaskCount($inspectorId, 'inspection');
            // ToDo: 承認者のカウンターをインクリメント
            $this->incrementPendingTaskCount($approverId); // メソッドを分けるか引数で判定

            // ToDo: 関連イベントを発行 (通知用)
            // event(new ApprovalRequested($ledgerDiff));

            Log::info("Inspection completed, approval requested for LedgerDiff ID: {$ledgerDiff->id}. Next approver: {$approverId}");

            return $ledgerDiff->refresh(); // 更新後のモデルを返す
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
