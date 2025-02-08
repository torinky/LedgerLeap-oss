<?php

namespace App\Notifications;

use App\Models\Ledger;
use App\Models\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

// 追記
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
        $notificationType = NotificationType::find($this->notificationTypeId);
        $allUsersRole = Role::where('name', 'All Users')->first();

        if (!$notificationType || !$allUsersRole) {
            Log::error('NotificationType or All Users role not found!');

            return [];
        }

        return [
            'notifiable_type' => get_class($allUsersRole), // Spatie\Permission\Models\Role
            'notifiable_id' => $allUsersRole->id,
            'type' => $notificationType->name,
            'data' => [
                'ledger_id' => $this->ledger->id,
                'ledger_name' => $this->ledger->name,
                'causer_name' => $this->additionalData['causer_name'] ?? null, // 追加
                'event' => $this->additionalData['event'] ?? null, // 追加
                // 他の必要なデータ
            ],
        ];
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
