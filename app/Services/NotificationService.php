<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\NotificationType;
use App\Models\Role;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;
use Log;

class NotificationService
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function processActivityLog(Ledger $ledger)
    {
        Log::info('NotificationService::processActivityLog called'); // ログ追加

        // activitylog を取得 (直近の1件のみ)
        $activity = $ledger->activities()->latest()->first();

        if (!$activity) {
            Log::info('No activity found');

            return;
        }

        Log::info('Activity found', ['activity' => $activity]); // ログ追加

        $notificationType = NotificationType::where('name', 'ledger_updated')->first();

        if (!$notificationType) {
            Log::info('NotificationType not found');

            return;
        }

        Log::info('NotificationType found', ['notificationType' => $notificationType]); // ログ追加

        // 通知対象のロールを取得 (UserService のメソッドを利用)
        $roles = $this->userService->getNotifiableRoles($activity->event, $ledger);
        //        $roles = Role::where('name', 'All Users')->get(); // App\Models\Role を取得

        if ($roles->isEmpty()) {
            Log::info('No roles to notify');
            return;
        }

        Log::info('Roles to notify', ['roles' => $roles]); // ログ追加

        // 通知を送信
        Notification::send($roles, new GenericNotification(
            $notificationType->id,
            $ledger,
            [
                'causer_id' => $activity->causer_id,
                'causer_name' => optional($activity->causer)->name,
                'event' => $activity->event,
            ]
        ));
    }
}
