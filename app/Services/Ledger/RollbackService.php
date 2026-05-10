<?php

namespace App\Services\Ledger;

use App\Enums\AttachedFileStatus;
use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Exceptions\Workflow\WorkflowConditionException;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\User;
use App\Services\UserService;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RollbackService
{
    public function __construct(
        protected WorkflowService $workflowService,
        protected UserService $userService
    ) {}

    /**
     * ロールバックが実行可能かチェックする
     *
     * @throws WorkflowConditionException
     */
    public function canExecute(User $user, Ledger $ledger): bool
    {
        // 1. 権限チェック (WRITE権限が必要)
        $folder = $ledger->define?->folder;
        if (! $folder || ! $this->userService->hasFolderPermission($user, $folder, FolderPermissionType::WRITE)) {
            return false;
        }

        // 2. 承認済み(レコードロック)状態のチェック
        if ($ledger->status === WorkflowStatus::APPROVED) {
            throw new WorkflowConditionException(__('ledger.error.operation_failed').': Record is locked.');
        }

        // 3. 進行中ワークフローの担当者チェック
        if ($ledger->status->isWorkflowPending()) {
            $latestDiff = $ledger->latestDiff()->first();
            if ($latestDiff) {
                $currentHandlerId = ($ledger->status === WorkflowStatus::PENDING_INSPECTION)
                    ? $latestDiff->inspector_id
                    : $latestDiff->approver_id;

                // 担当者が割り当てられている場合、その本人でないとロールバック（編集扱い）不可
                if ($currentHandlerId && $currentHandlerId !== $user->id) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * ロールバックを実行する
     *
     * @throws \Throwable
     */
    public function execute(Ledger $ledger, LedgerDiff $targetDiff, User $modifier, ?string $comments, int $expectedVersion): Ledger
    {
        // 1. 準備チェック
        if (! $this->canExecute($modifier, $ledger)) {
            throw new \Exception(__('ledger.errors.no_permission_to_claim'));
        }

        // 2. 排他制御 (楽観的ロック)
        if ($ledger->version !== $expectedVersion) {
            throw new WorkflowConditionException(__('ledger.errors.cannot_execute_action').' (Version mismatch)');
        }

        return DB::transaction(function () use ($ledger, $targetDiff, $modifier, $comments) {
            $currentStatus = $ledger->status;
            $latestDiffBefore = $ledger->latestDiff()->first();
            $newVersion = $ledger->version + 1;

            // 3. 添付情報の再構成 (DB容量節約のため ledger_diffs.content_attached は使用しない)
            // 既存の Ledger.content_attached は AttachedFile モデルと整合しているはずなので、
            // ロールバック対象の各レコード（AttachedFile）の状態から content_attached を動的に再生成する
            $reconstructedContentAttached = $this->reconstructContentAttached($ledger, $targetDiff);

            // 4. 新しい LedgerDiff の作成
            $newDiff = LedgerDiff::create([
                'ledger_id' => $ledger->id,
                'ledger_define_id' => $ledger->ledger_define_id,
                'content' => $targetDiff->content, // 過去のコンテンツをコピー
                'column_define' => $ledger->define->column_define, // 最新の定義を使用
                'version' => $newVersion,
                'status' => WorkflowStatus::DRAFT, // ロールバック後は下書きに戻る
                'creator_id' => $ledger->creator_id,
                'modifier_id' => $modifier->id,
                'comments' => $comments,
                'returned_at' => now(), // ロールバック(実質的な差し戻し/編集)日時
                // 担当者は現在のものを引き継ぐ
                'inspector_id' => $latestDiffBefore->inspector_id ?? null,
                'approver_id' => $latestDiffBefore->approver_id ?? null,
                'completed_inspector_role_ids' => [],
                'completed_approver_role_ids' => [],
            ]);

            // 5. Ledger の更新
            $ledger->update([
                'content' => $targetDiff->content,
                'content_attached' => $reconstructedContentAttached,
                'status' => WorkflowStatus::DRAFT,
                'version' => $newVersion,
                'modifier_id' => $modifier->id,
                'latest_diff_id' => $newDiff->id,
            ]);

            // 6. ワークフローカウンターのデクリメント (PENDING状態からDRAFTに戻った場合)
            if ($ledger->status->isWorkflowPending() && $latestDiffBefore) {
                $handlerId = ($currentStatus === WorkflowStatus::PENDING_INSPECTION)
                    ? $latestDiffBefore->inspector_id
                    : $latestDiffBefore->approver_id;

                if ($handlerId) {
                    $taskType = ($currentStatus === WorkflowStatus::PENDING_INSPECTION) ? 'inspection' : 'approval';
                    $this->workflowService->decrementPendingTaskCount($handlerId, $taskType);
                }
            }

            // 7. ジョブの投入 (スコア再計算、検索インデックス更新)
            // ToDo: ジョブクラスが実装されたらここに追加

            Log::info("Ledger Rollback Executed. ID: {$ledger->id}, TargetVersion: {$targetDiff->version}, NewVersion: {$newVersion}");

            return $ledger->refresh();
        });
    }

    /**
     * ロールバック対象のコンテンツに基づいて AttachedFile の状態から content_attached を再構成する
     */
    protected function reconstructContentAttached(Ledger $ledger, LedgerDiff $targetDiff): array
    {
        $targetContent = $targetDiff->content;
        $reconstructed = [];

        // 台帳定義のカラムを走査してファイルを抽出
        foreach ($ledger->define->column_define as $column) {
            if ($column->type === 'files') {
                $columnId = $column->id;

                $filesData = $targetContent[$columnId] ?? [];

                if (is_array($filesData)) {
                    $columnReconstructed = [];
                    foreach (array_keys($filesData) as $hashedBasename) {
                        // データベースの AttachedFile レコードから最新のメタデータを取得
                        $attachedFile = AttachedFile::where('ledger_id', $ledger->id)
                            ->where('hashedbasename', $hashedBasename)
                            ->first();

                        if ($attachedFile) {
                            $meta = $attachedFile->tika_metadata;

                            // Auto-healing: データ消失の検知と復旧
                            // 現在の台帳からメタデータ(テキスト本文)が失われている場合、再処理をトリガーする
                            $isTextMissing = empty($meta['content']) && $attachedFile->isVlmOrOcrTarget();

                            if ($isTextMissing) {
                                Log::warning("RollbackService: Tika/OCR content missing for file ID {$attachedFile->id}. Triggering reprocessing.", [
                                    'ledger_id' => $ledger->id,
                                    'hashedbasename' => $hashedBasename,
                                ]);

                                // ステータスをリセットして再処理を予約
                                // トランザクション内での実行だが、ジョブ投入(非同期)なので問題ないはず
                                // ただし、モデル更新はここで行う
                                $attachedFile->update([
                                    'status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING,
                                    'tika_processed_at' => null,
                                    'processing_finalized_at' => null,
                                ]);

                                ProcessAttachedFile::dispatch($attachedFile);
                            }

                            // Tika/OCR等の解析情報も含めた完全な構造を再構築
                            $columnReconstructed[$hashedBasename] = [
                                'filename' => $attachedFile->filename,
                                'meta' => $meta,
                            ];
                        }
                    }
                    $reconstructed[$columnId] = $columnReconstructed;
                }
            }
        }

        // LedgerDefine.normalizeByColumnDefine を使用して構造を正規化(欠番埋め)
        return $ledger->define->normalizeByColumnDefine($reconstructed);
    }
}
