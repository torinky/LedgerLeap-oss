<?php

namespace App\Services;

use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

class NotificationService
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    // public function processActivityLog(Ledger $ledger) // 引数を変更
    public function processActivityLog(Activity $activity)
    {
        Log::info('NotificationService::processActivityLog called'); // ログ追加

        // subject_type に応じて通知タイプを決定
        $subjectType = $activity->subject_type;
        $event = $activity->event;

        // 通知タイプを取得
        $notificationTypeName = null;
        switch ($subjectType) {
            case 'App\Models\Ledger':
                $notificationTypeName = "ledger_{$event}";
                break;
            // case 'App\Models\User':
            //     $notificationTypeName = "user_{$event}";
            //     break;
            // 他のモデルのケースを追加
            default:
                Log::info("Unknown subject type: {$subjectType}");

                return;
        }

        $notificationType = NotificationType::where('name', $notificationTypeName)->first();

        if (!$notificationType) {
            Log::info("NotificationType not found for event: {$notificationTypeName}");

            return;
        }

        Log::info('NotificationType found', ['notificationType' => $notificationType]); // ログ追加

        // 通知対象のロールを取得 (UserService のメソッドを利用)
        $roles = $this->userService->getNotifiableRoles($activity->event, $activity->subject);

        if ($roles->isEmpty()) {
            Log::info('No roles to notify');

            return;
        }

        Log::info('Roles to notify', ['roles' => $roles]); // ログ追加

        //        dd($activity);
        // 通知を送信
        Notification::send($roles, new GenericNotification(
            $notificationType->id,
            $activity->subject, // 変更されたモデルのインスタンスを渡す
            $activity
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
