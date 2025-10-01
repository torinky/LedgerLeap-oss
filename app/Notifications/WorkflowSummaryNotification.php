<?php

namespace App\Notifications;

// Namespace は適切に設定

use App\Mail\WorkflowSummaryMail;
use App\Models\User;
// User を use
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
// メール通知も考慮する場合
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

// Log を use

class WorkflowSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $inspectionCount;

    public int $approvalCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $inspectionCount, int $approvalCount)
    {
        $this->inspectionCount = $inspectionCount;
        $this->approvalCount = $approvalCount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  User  $notifiable  User オブジェクトを受け取る想定
     * @return array<int, string>
     */
    public function via(object $notifiable): array // 引数の型を User に変更 (推奨)
    {
        $channels = ['database'];

        // $notifiable が User インスタンスで、かつメール通知権限を持っているか確認
        if ($notifiable instanceof User && $notifiable->can('receive_workflow_summary_email')) {
            $channels[] = 'mail';
        }
        Log::info("Notification channels for User ID: {$notifiable->id}, Type: WorkflowSummaryNotification", ['channels' => $channels]);

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  User  $notifiable  User オブジェクトを受け取る想定
     */
    public function toMail(object $notifiable): ?WorkflowSummaryMail // 戻り値を Mailable クラスに、Nullable に
    {
        // $notifiable が User インスタンスでない場合は null を返す (念のため)
        if (! $notifiable instanceof User) {
            Log::warning('toMail called with non-User notifiable.', ['notifiable_type' => get_class($notifiable)]);

            return null;
        }

        Log::info("Generating WorkflowSummaryMail for User ID: {$notifiable->id}");

        // WorkflowSummaryMail インスタンスを生成して返す
        return new WorkflowSummaryMail(
            $this->inspectionCount,
            $this->approvalCount
        )->to($notifiable->email); // 受信者のメールアドレスを指定
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array // $notifiable は User オブジェクト
    {
        $totalCount = $this->inspectionCount + $this->approvalCount;
        $message = __('ledger.workflow.summary_notification_message', [
            'inspection_count' => $this->inspectionCount,
            'approval_count' => $this->approvalCount,
        ]);

        Log::info("WorkflowSummaryNotification::toDatabase called for User ID: {$notifiable->id}");

        return [
            // data カラムに格納される配列
            'message' => $message,
            'total_count' => $totalCount,
            'inspection_count' => $this->inspectionCount,
            'approval_count' => $this->approvalCount,
            'link' => route('notifications.index', ['tab' => 'tasks']), // リンク先
            // GenericNotification と形式を合わせる場合
            /*
            'payload' => [
                'notification_type_name' => 'workflow_summary', // タイプ名をペイロードに含める
                'message' => $message,
                'total_count' => $totalCount,
                'inspection_count' => $this->inspectionCount,
                'approval_count' => $this->approvalCount,
                'route' => 'notifications.index',
                'route_params' => ['tab' => 'tasks'], // パラメータを分離
            ]
            */
        ];
    }

    /**
     * Get the array representation of the notification. (toBroadcast などで利用)
     */
    // public function toArray(object $notifiable): array
    // {
    //    return $this->toDatabase($notifiable);
    // }
}
