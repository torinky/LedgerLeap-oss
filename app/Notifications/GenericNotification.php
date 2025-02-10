<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\NotificationType;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class GenericNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected int $notificationTypeId;
    protected Model $subject; // 追加: 通知対象オブジェクト (Ledger, Folder, User など)
    protected Activity $activity; // 追加: Activity モデル

    /**
     * Create a new notification instance.
     *
     * @param int $notificationTypeId
     * @param Model $subject // 型ヒントを Model に変更
     * @param array $activity // 追加
     * @return void
     */
    public function __construct(int $notificationTypeId, Model $subject, Activity $activity)
    {
        $this->notificationTypeId = $notificationTypeId;
        $this->subject = $subject;
        $this->activity = $activity;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database']; // データベースのみ
    }

    /**
     * Get the database representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
//        dd($notifiable); // 追加: $notifiable の内容を確認
        Log::info('GenericNotification::toDatabase called', ['notifiable' => $notifiable]);

        $notificationType = NotificationType::find($this->notificationTypeId);

        if (!$notificationType || !$notifiable) {
            Log::error('NotificationType or Notifiable role not found!');
            return [];
        }

        // notifications テーブルへのレコード追加 (ロールに対して通知を登録)
        $notificationData = [
            'notifiable_type' => get_class($notifiable), // Spatie\Permission\Models\Role
            'notifiable_id' => $notifiable->id,
            'type' => $notificationType->name,
            'payload' => [
                'subject_type' => get_class($this->subject),
                'subject_id' => $this->subject->id,
                'causer_name' => optional($this->activity->causer)->name,
                'event' => $this->activity->event,
                'description' => $this->activity->description,
                'changes' => $this->activity->changes(),
            ],
        ];

        Log::info('Notification data', ['data' => $notificationData]);

        return $notificationData;


    }

}
