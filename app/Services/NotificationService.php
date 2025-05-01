<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
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

    public function processActivityLog(Activity $activity)
    {
        Log::info('NotificationService::processActivityLog called');

        $subjectType = $activity->subject_type;
        $event = $activity->event;
        // 通知タイプを取得 (model と event で絞り込み)
        $notificationType = NotificationType::where('model', $subjectType)
            ->where('event', $event)
            ->first();

        if (!$notificationType) {
            Log::info("NotificationType not found for model: {$subjectType}, event: {$event}");

            return;
        }

        // 通知対象のロールとユーザーを取得
        $users = $this->getNotifiableRecipients($activity, $notificationType);

        if ($users->isEmpty()) {
            Log::info("users not found for model: {$subjectType}, event: {$event}");

            return;
        }

        Log::info('NotificationType found', ['notificationType' => $notificationType]);
        Log::info('Event', ['event' => $event]);
        // 通知を送信
        Notification::send($users, new GenericNotification(
            $notificationType->id,
            $activity->subject,
            $activity
        ));
    }

    public function getUnreadNotificationsForUser(User $user, int $perPage = 10): LengthAwarePaginator
    {
        return $user->unreadNotifications()->paginate($perPage);
    }

    public function getUnreadNotificationCountForUser(User $user): int
    {
        return $this->unreadNotificationsForUser($user)->count();
//        return $user->unreadNotifications()->count();
    }

    /*    public function getUnreadNotificationsForUser(User $user, int $perPage = 10): LengthAwarePaginator
        {
            return $this->unreadNotificationsForUser($user)->paginate($perPage);
        }

        public function getUnreadNotificationCountForUser(User $user): int
        {
            return $this->unreadNotificationsForUser($user)->count();
        }*/

    // 既読処理 (単数/複数)
    public function markAsRead(User $user, $notificationIds = null): void
    {
        if ($notificationIds === null) {
            // $notificationIds が null の場合、すべての未読通知を対象にする
            $notifications = $this->unreadNotificationsForUser($user)->get();
        } elseif (is_string($notificationIds)) {
            // $notificationIds が文字列の場合、単一の通知 ID として扱う
            $notifications = $this->unreadNotificationsForUser($user)->where('id', $notificationIds)->get();
        } else {
            // $notificationIds が配列の場合
            $notifications = $this->unreadNotificationsForUser($user)->whereIn('id', $notificationIds)->get();
        }

        foreach ($notifications as $notification) {
            $notificationUser = DB::table('notification_user')
                ->where('notification_id', $notification->id)
                ->where('user_id', $user->id)
                ->first();

            if ($notificationUser) {
                // レコードが存在する場合は、read_at を更新
                DB::table('notification_user')
                    ->where('notification_id', $notification->id)
                    ->where('user_id', $user->id)
                    ->update(['read_at' => now()]);
            } else {
                // レコードが存在しない場合は、新規作成
                DB::table('notification_user')->insert([
                    'notification_id' => $notification->id,
                    'user_id' => $user->id,
                    'read_at' => now(), // 既読日時をセット
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function unreadNotificationsForUser(User $user): Builder
    {
        $userService = $this->userService;
        $roles = $userService->getAllUniqueRolesForUser($user);

        return DatabaseNotification::query()
            ->where(function ($query) use ($user, $roles) {
                $query->where(function ($q) use ($roles) {
                    $q->where('notifiable_type', Role::class)
                        ->whereIn('notifiable_id', $roles->pluck('id'));
                })->orWhere(function ($q) use ($user) {
                    $q->where('notifiable_type', get_class($user))
                        ->where('notifiable_id', $user->id);
                });
            })
            ->whereNotExists(function ($query) use ($user) {
                $query->select(DB::raw(1))
                    ->from('notification_user')
                    ->whereColumn('notification_user.notification_id', 'notifications.id')
                    ->where('notification_user.user_id', $user->id);
            })->orderBy('created_at', 'desc');
    }

    public function getNotifiableRecipients(Activity $activity, NotificationType $notificationType): Collection
    {
        $subject = $activity->subject;
        Log::info('NotificationService::getNotifiableRecipients called', ['subject' => $subject, 'activity' => $activity]);
        // subject (変更されたモデル) からフォルダーを特定し、その子孫フォルダーも取得
        $folder = $subject->folder()->first();

        if (!$folder) {
            \Log::info('Folder not found for subject: ' . get_class($subject));
            return collect();
        }

        // 先祖フォルダーを取得
        $ancestorFolders = $folder->ancestorsAndSelf($folder->id);
        // 自分自身と先祖フォルダーのIDを配列に格納
        $folderIds = $ancestorFolders->pluck('id')->toArray();

        // フォルダーと通知タイプに紐づく RoleFolderPermission を取得し、permission が NOTIFY_ON のものを抽出
        $roleFolderPermissions = RoleFolderPermission::whereIn('folder_id', $folderIds)
            ->where('notification_type_id', $notificationType->id)
            ->where('permission', FolderPermissionType::NOTIFY_ON)
            ->get();

        // RoleFolderPermission から Role の ID を取得
        $roleIds = $roleFolderPermissions->pluck('role_id')->unique()->toArray();

        // Role の ID から User を取得 (UserService を利用)
        $users = $this->userService->getUsersByRoleIds($roleIds);

        return $users;
    }

    /**
     * 通知対象のロールを取得する
     */
    public function getNotifiableRoles(Activity $activity, NotificationType $notificationType): Collection // 引数を修正
    {
        $subject = $activity->subject;

        // subject (変更されたモデル) からフォルダーを特定
        $folder = null;
        //        dd($activity->subject_type);
        /*        if ($activity->subject_type == Ledger::class) {
                    $folder = $activity->subject->define->folder;
                }*/
        // TODO: Folder モデルや User モデルの変更の場合は、$activity->subject から直接取得

        if (!$folder) {
            \Log::info('Folder not found for subject: ' . $activity->subject_type);

            return collect();
        }

        // フォルダーと通知タイプに紐づく RoleFolderPermission を取得し、permission が NOTIFY_ON のものを抽出
        $roleFolderPermissions = RoleFolderPermission::where('folder_id', $folder->id)
            ->where('notification_type_id', $notificationType->id)
            ->where('permission', FolderPermissionType::NOTIFY_ON)
            ->get();

        // RoleFolderPermission から Role の ID を取得
        $roleIds = $roleFolderPermissions->pluck('role_id')->unique()->toArray();

        // Role の ID から Role モデルを取得
        return Role::whereIn('id', $roleIds)->get();
    }

    /**
     * ワークフロー関連の個別通知を送信する
     *
     * @param User $recipient
     * @param NotificationType $notificationType
     * @param LedgerDiff $ledgerDiff
     * @param string|null $comment
     * @param Folder|null $folder 対象フォルダ
     */
    public function sendWorkflowNotification(User $recipient, NotificationType $notificationType, LedgerDiff $ledgerDiff, ?string $comment = null, ?Folder $folder = null): void
    {
        Log::info("sendWorkflowNotification called for User ID: {$recipient->id}, Type: {$notificationType->name}, Diff ID: {$ledgerDiff->id}");

        // 1. ユーザーがこの通知タイプを受け取る設定か確認する
        //    (shouldReceiveNotification の第3引数に Folder オブジェクトを渡す)
        if (!$this->shouldReceiveNotification($recipient, $notificationType, $folder)) { // <<<--- $folder を渡す
            Log::info("User {$recipient->id} should not receive notification type {$notificationType->name} for this folder.");
            return;
        }

        // 2. 通知を送信 (GenericNotification を流用)
        try {
            $causer = $ledgerDiff->modifier; // 操作者
            $subject = $ledgerDiff->ledger; // 対象
            // イベント名は NotificationType の名前を使うか、別途定義する
            $eventName = $notificationType->event ?? $notificationType->name;

            // GenericNotification の payload にコメントなどの追加情報を渡す
            $payloadOverrides = [];
            if ($comment) {
                $payloadOverrides['comments'] = $comment;
            }
            // 次の担当者情報なども必要なら payload に含める
            if ($notificationType->name === 'inspection_requested') {
                $payloadOverrides['inspector_id'] = $ledgerDiff->inspector_id;
            }

            Notification::send($recipient, new GenericNotification(
                notificationTypeId: $notificationType->id,
                subject: $subject,
                activity: null, // <<<--- Activity は null
                causer: $causer, // <<<--- 操作者 User を渡す
                eventName: $eventName, // <<<--- イベント名を渡す
                comment: $comment, // <<<--- コメントを渡す
                payloadOverrides: $payloadOverrides
            ));
            Log::info("Workflow notification sent successfully.", ['recipient_id' => $recipient->id, 'type' => $notificationType->name]);
        } catch (\Exception $e) {
            Log::error("Failed to send workflow notification: " . $e->getMessage(), [
                'recipient_id' => $recipient->id,
                'type' => $notificationType->name,
                'diff_id' => $ledgerDiff->id,
                'exception' => $e
            ]);
        }
    }

    /**
     * ユーザーが特定のフォルダで特定の通知タイプを受け取る設定か確認するヘルパー
     *
     * @param User $user
     * @param NotificationType $notificationType
     * @param Folder|null $folder 対象フォルダ (null の場合はグローバル設定？ or 失敗？)
     * @return bool
     */
    protected function shouldReceiveNotification(User $user, NotificationType $notificationType, ?Folder $folder): bool
    {
        if (!$folder) {
            // workflow_summary などフォルダに依存しない通知タイプの扱い (要検討)
            // とりあえず false を返すか、グローバル設定を見る
            if ($notificationType->name === 'workflow_summary') {
                // ToDo: グローバルな通知設定を確認するロジック
                return true; // 仮に常に true
            }
            Log::warning("Folder context is missing for notification check.", ['user_id' => $user->id, 'type' => $notificationType->name]);
            return false;
        }

        // ユーザーの全有効ロールを取得
        $userRoles = $this->userService->getAllUniqueRolesForUser($user);
        if ($userRoles->isEmpty()) {
            return false;
        }
        $roleIds = $userRoles->pluck('id')->toArray();

        // フォルダとその祖先の ID リストを取得
        $folderIds = $folder->ancestorsAndSelf($folder->id)->pluck('id')->toArray();

        // 該当する RoleFolderPermission で NOTIFY_ON になっているか確認
        $canReceive = RoleFolderPermission::whereIn('role_id', $roleIds)
            ->whereIn('folder_id', $folderIds)
            ->where('notification_type_id', $notificationType->id)
            ->where('permission', FolderPermissionType::NOTIFY_ON)
            ->exists();

        return $canReceive;
    }

}
