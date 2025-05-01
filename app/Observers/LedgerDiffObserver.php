<?php

namespace App\Observers;

use App\Enums\WorkflowStatus;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class LedgerDiffObserver
{
    protected NotificationService $notificationService;

    // NotificationService をインジェクト
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the LedgerDiff "saved" event.
     * (created と updated の両方で発火)
     */
    public function saved(LedgerDiff $ledgerDiff): void
    {
        // ステータスが変更された場合、または特定のステータスで新規作成された場合
        if ($ledgerDiff->wasChanged('status') || $ledgerDiff->wasRecentlyCreated) {
            Log::info("LedgerDiffObserver saved event triggered for Diff ID: {$ledgerDiff->id}, Status: {$ledgerDiff->status->value}, Changed: " . json_encode($ledgerDiff->getChanges()));

            $applicant = $ledgerDiff->ledger?->creator; // 申請者
            $modifier = $ledgerDiff->modifier; // 今回の操作者
            $inspector = $ledgerDiff->inspector; // 点検担当者
            $approver = $ledgerDiff->approver; // 承認担当者
            $comment = $ledgerDiff->comments; // コメント
            $folder = $ledgerDiff->ledger?->define?->folder; // 関連フォルダ (通知設定確認用)

            if (!$applicant) {
                Log::error("Applicant not found for LedgerDiff ID: {$ledgerDiff->id}");
                return;
            }
            if (!$folder) {
                Log::warning("Folder not found for LedgerDiff ID: {$ledgerDiff->id}. Cannot check notification settings.");
                // フォルダがない場合でも通知する？ or しない？ -> しない方が安全か
                // return;
            }


            $notificationTypeName = null;
            $recipient = null;
            $eventName = $ledgerDiff->status->value; // イベント名としてステータス名を仮利用

            // ステータスに基づいて通知タイプと受信者を決定
            switch ($ledgerDiff->status) {
                case WorkflowStatus::PENDING_INSPECTION:
                    // 点検依頼の通知 (オプション)
                    $notificationTypeName = 'inspection_requested';
                    $recipient = $inspector;
                    break;
                case WorkflowStatus::PENDING_APPROVAL:
                    // 承認依頼の通知 (オプション)
                    $notificationTypeName = 'approval_requested';
                    $recipient = $approver;
                    // 点検完了通知 (申請者向け - オプション)
                    if ($ledgerDiff->wasChanged('status')) { // ステータスが変わった場合のみ
                        $this->sendNotificationToApplicant($applicant, 'inspection_completed', $ledgerDiff, null, $folder);
                    }
                    break;
                case WorkflowStatus::APPROVED:
                    // 承認完了通知 (申請者向け)
                    $notificationTypeName = 'approved';
                    $recipient = $applicant;
                    break;
                case WorkflowStatus::DRAFT:
                    // 作成中に戻された通知 (申請者向け)
                    // returned_at が更新されたか、または comments が付与されたかで判断？
                    if ($ledgerDiff->wasChanged('returned_at') && $ledgerDiff->returned_at !== null) {
                        $notificationTypeName = 'status_returned_to_draft';
                        $recipient = $applicant;
                        // $comment は $ledgerDiff->comments をそのまま使う
                    }
                    break;
                // case WorkflowStatus::NONE: // WF無効時はObserverで何もしない
                default:
                    break;
            }

            // 特定の通知タイプが見つかり、受信者がいれば通知を試みる
            if ($notificationTypeName && $recipient) {
                $notificationType = NotificationType::where('name', $notificationTypeName)->first();
                if ($notificationType) {
                    Log::info("Attempting to send notification.", ['type' => $notificationTypeName, 'recipient_id' => $recipient->id, 'diff_id' => $ledgerDiff->id]);
                    // sendWorkflowNotification の呼び出し
                    $this->notificationService->sendWorkflowNotification($recipient, $notificationType, $ledgerDiff, $comment, $folder);
                } else {
                    Log::warning("NotificationType not found for name: {$notificationTypeName}");
                }
            } else {
                Log::info("Notification conditions not met or recipient not found.", ['status' => $ledgerDiff->status->value, 'type_name' => $notificationTypeName, 'recipient_exists' => !is_null($recipient), 'diff_id' => $ledgerDiff->id]);
            }
        }
    }

    /**
     * 申請者への通知送信ヘルパー (オプション)
     */
    private function sendNotificationToApplicant(?User $applicant, string $notificationTypeName, LedgerDiff $ledgerDiff, ?string $comment = null, ?Folder $folder = null): void
    {
        if (!$applicant) return;
        $notificationType = NotificationType::where('name', $notificationTypeName)->first();
        if ($notificationType) {
            $this->notificationService->sendWorkflowNotification($applicant, $notificationType, $ledgerDiff, $comment, $folder);
        } else {
            Log::warning("NotificationType not found for applicant: {$notificationTypeName}");
        }
    }

    /**
     * Handle the LedgerDiff "created" event.
     */
    public function created(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "updated" event.
     */
    public function updated(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "deleted" event.
     */
    public function deleted(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "restored" event.
     */
    public function restored(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "force deleted" event.
     */
    public function forceDeleted(LedgerDiff $ledgerDiff): void
    {
        //
    }
}
