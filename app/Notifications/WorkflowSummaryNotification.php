<?php

namespace App\Notifications;
// Namespace は適切に設定

use App\Models\User;

// User を use
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

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
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // ここで通知チャネルを決定 (DBは必須)
        $channels = ['database'];
        // if ($notifiable->prefers_browser_notifications) { // 将来的なブラウザ通知
        //     $channels[] = 'broadcast'; // または WebPush チャンネル
        // }
        // if ($notifiable->prefers_email_summary) { // メール通知オプション
        //     $channels[] = 'mail';
        // }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //                 ->line('未処理のワークフロータスクがあります。')
    //                 ->line("点検待ち: {$this->inspectionCount} 件")
    //                 ->line("承認待ち: {$this->approvalCount} 件")
    //                 ->action('タスクを確認する', route('notifications.index', ['tab' => 'tasks']))
    //                 ->line('ご確認ください。');
    // }

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