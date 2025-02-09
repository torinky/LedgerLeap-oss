<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\BroadcastMessage;
use App\Models\Ledger;
use App\Models\NotificationType;
use App\Models\User;
use Log;
use Spatie\Permission\Models\Role;

class GenericNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $notificationTypeId;
    protected $ledger;
    protected $additionalData;

    /**
     * Create a new notification instance.
     *
     * @param int $notificationTypeId
     * @param Ledger $ledger
     * @param array $additionalData
     * @return void
     */
    public function __construct(int $notificationTypeId, Ledger $ledger, array $additionalData = [])
    {
        $this->notificationTypeId = $notificationTypeId;
        $this->ledger = $ledger;
        $this->additionalData = $additionalData;
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
        Log::info('GenericNotification::toDatabase called', ['notifiable' => $notifiable]);

        $notificationType = NotificationType::find($this->notificationTypeId);

        if (!$notificationType || !$notifiable) {
            Log::error('NotificationType or Notifiable role not found!');
            return []; // または null
        }

        // notifications テーブルへのレコード追加 (ロールに対して通知を登録)
        $notificationData = [
            'notifiable_type' => get_class($notifiable), // Spatie\Permission\Models\Role
            'notifiable_id' => $notifiable->id,
            'type' => $notificationType->name,
            'data' => [
                'ledger_id' => $this->ledger->id,
                'ledger_name' => $this->ledger->name,
                'causer_name' => $this->additionalData['causer_name'] ?? null,
                'event' => $this->additionalData['event'] ?? null,
            ],
        ];

        Log::info('Notification data', ['data' => $notificationData]);

        return $notificationData; // $notificationData を返す
    }
    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'ledger_id' => $this->ledger->id,
            'read_at' => null,
            // 他の必要なデータ
        ];
    }
}
