<?php

namespace App\Services;

use App\Models\Ledger;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Support\Collection;

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

        if ($roles->isEmpty()) {
            Log::info('No roles to notify');
            return;
        }

        Log::info('Roles to notify', ['roles' => $roles]); // ログ追加

//        dd($activity);
        // 通知を送信
        Notification::send($roles, new GenericNotification(
            $notificationType->id,
            $ledger,
            $activity // $activity を渡す
        ));
    }

    public function getUnreadNotificationsForUser(User $user): Collection
    {
        return $user->unreadNotifications()->get();
    }

    public function getUnreadNotificationCountForUser(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markNotificationAsRead(string $notificationId, User $user): void
    {
        // notification_user テーブルにレコードが存在するか確認
        $notificationUser = DB::table('notification_user')
            ->where('notification_id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if ($notificationUser) {
            // レコードが存在する場合は、read_at を更新
            DB::table('notification_user')
                ->where('notification_id', $notificationId)
                ->where('user_id', $user->id)
                ->update(['read_at' => now()]);
        } else {
            // レコードが存在しない場合は、新規作成
            DB::table('notification_user')->insert([
                'notification_id' => $notificationId,
                'user_id' => $user->id,
                'read_at' => now(), // 既読日時をセット
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
