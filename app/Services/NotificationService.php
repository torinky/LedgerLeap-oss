<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Notifications\WorkflowSummaryNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
//        Log::info('NotificationService::getNotifiableRecipients called', ['subject' => $subject, 'activity' => $activity]);
        // subjectとactivityオブジェクト全体ではなく、クラス名とIDのみをログに出力して簡潔にします。
        Log::info('NotificationService::getNotifiableRecipients called', [
            'subject_class' => get_class($subject),
            'subject_id' => $subject->id,
            'activity_id' => $activity->id,
        ]);
        // subject (変更されたモデル) からフォルダーを特定し、その子孫フォルダーも取得
        if ($subject instanceof Ledger) {
            $folder = $subject->define->folder;
        } elseif ($subject instanceof LedgerDefine) {
            $folder = $subject->folder;
        } elseif ($subject instanceof Folder) {
            $folder = $subject;
        } else {
            $folder = null;
        }

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
     * 変更点: メール送信の可否は GenericNotification::via() で判断されるため、
     *         このメソッド内でのメール送信 Permission チェックは不要。
     *         ただし、システム内通知を送るかどうかの判断のために
     *         shouldReceiveNotification (RoleFolderPermission チェック) は維持する。
     *
     * @param User $recipient
     * @param NotificationType $notificationType
     * @param LedgerDiff $ledgerDiff
     * @param string|null $comment
     * @param Folder|null $folder 対象フォルダ
     */
//    public function sendWorkflowNotification(User $recipient, NotificationType $notificationType, LedgerDiff $ledgerDiff, ?string $comment = null, ?Folder $folder = null): void
//    {
//        Log::info("Attempting to send workflow notification (system/mail) for User ID: {$recipient->id}, Type: {$notificationType->name}, Diff ID: {$ledgerDiff->id}");
//
//        // 1. ユーザーがこのフォルダでこの通知タイプを *システム内通知で* 受け取る設定か確認
//        if (!$this->shouldReceiveNotification($recipient, $notificationType, $folder)) {
//            Log::info("User {$recipient->id} should not receive system notification type {$notificationType->name} for this folder.");
//            // メールだけ送りたい場合はこの return を消すが、基本はシステム通知と連動させる
//            return;
//        }
//
//        // 2. 通知を送信 (GenericNotification インスタンスを渡すだけ)
//        try {
//            $causer = $ledgerDiff->modifier;
//            $subject = $ledgerDiff->ledger;
//            $eventName = $notificationType->event ?? $notificationType->name;
//            $payloadOverrides = [];
//            if ($comment) $payloadOverrides['comments'] = $comment;
//            if ($notificationType->name === 'inspection_requested') $payloadOverrides['inspector_id'] = $ledgerDiff->inspector_id;
//            if ($notificationType->name === 'approval_requested') $payloadOverrides['approver_id'] = $ledgerDiff->approver_id;
//
//            // GenericNotification をインスタンス化
//            $notification = new GenericNotification(
//                notificationTypeId: $notificationType->id,
//                subject: $subject, // subject は Ledger になっているはず
//                activity: null,
//                causer: $causer,
//                eventName: $eventName,
//                comment: $comment,
//                payloadOverrides: $payloadOverrides
//            );
//
//            // Notification Facade を使って送信
//            Notification::send($recipient, $notification);
//
//            Log::info("Workflow notification (system/mail if permitted) dispatched successfully.", ['recipient_id' => $recipient->id, 'type' => $notificationType->name]);
//        } catch (\Exception $e) {
//            Log::error("Failed to dispatch workflow notification: " . $e->getMessage(), [
//                'recipient_id' => $recipient->id, 'type' => $notificationType->name, 'diff_id' => $ledgerDiff->id, 'exception' => $e
//            ]);
//        }
//    }

    /**
     * ワークフロー関連の通知を送信する (修正: task_claimed に対応)
     *
     * @param User $recipient
     * @param NotificationType $notificationType
     * @param Model $subject Ledger (task_claimed の場合) または LedgerDiff
     * @param string|null $comment
     * @param Folder|null $folder
     * @param User|null $originalAssignee 元の担当者 (task_claimed の場合)
     */
    public function sendWorkflowNotification(
        User $recipient,
        NotificationType $notificationType,
        Model $subject, // Ledger or LedgerDiff
        ?string $comment = null,
        ?Folder $folder = null,
        ?User $originalAssignee = null // task_claimed 用に追加
    ): void
    {
        Log::info("Attempting to send workflow notification for User ID: {$recipient->id}, Type: {$notificationType->name}");

        // 1. システム内通知の可否チェック (変更なし)
        if (!$this->shouldReceiveNotification($recipient, $notificationType, $folder)) {
            Log::info("User {$recipient->id} should not receive system notification type {$notificationType->name} for this folder.");
            return;
        }

        // 2. 通知送信
        try {
            $causer = null; //GenericNotification 側でsubjectのmodifierを使ってもらう
            if ($subject instanceof Ledger || $subject instanceof LedgerDiff) {
                // $subject->modifier はリレーションオブジェクトを返す
                // ここで User インスタンスを取得する必要がある
                $causer = $subject->modifier()->first(); // ★ 修正: ->first() を追加
            }

            $eventName = $notificationType->event ?? $notificationType->name;
            $payloadOverrides = [];
            if ($comment) $payloadOverrides['comments'] = $comment;

            if ($notificationType->name === 'task_claimed') {
                // $subject は Ledger, $causer は引き継ぎ操作者, $originalAssignee を渡す
                $payloadOverrides['original_assignee_id'] = $originalAssignee?->id;
                $payloadOverrides['new_assignee_id'] = $causer?->id; // 引き継ぎ操作者が新しい担当者
            } elseif ($subject instanceof LedgerDiff) {
                if ($notificationType->name === 'inspection_requested') $payloadOverrides['inspector_id'] = $subject->inspector_id;
                if ($notificationType->name === 'approval_requested') $payloadOverrides['approver_id'] = $subject->approver_id;
            }

            $notification = new GenericNotification(
                notificationTypeId: $notificationType->id,
                subject: $subject, // Ledger または LedgerDiff
                activity: null, // Activity Log は使わない前提
                causer: $causer,
                eventName: $eventName,
                comment: $comment,
                payloadOverrides: $payloadOverrides,
                originalAssignee: ($notificationType->name === 'task_claimed') ? $originalAssignee : null // task_claimed の場合のみ渡す
            );

            Notification::send($recipient, $notification);
            Log::info("Workflow notification (system/mail if permitted) dispatched successfully.", ['recipient_id' => $recipient->id, 'type' => $notificationType->name]);

        } catch (\Exception $e) {
            if($subject instanceof Ledger){
                Log::error("Failed to dispatch workflow notification: " . $e->getMessage(), [
                    'recipient_id' => $recipient->id, 'type' => $notificationType->name, 'ledger_id' => $subject->id, 'exception' => $e
                ]);

            }elseif($subject instanceof LedgerDiff){
                Log::error("Failed to dispatch workflow notification: " . $e->getMessage(), [
                    'recipient_id' => $recipient->id, 'type' => $notificationType->name, 'diff_id' => $subject->id, 'exception' => $e
                ]);
            }
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

    /**
     * ワークフローの未処理タスク集約通知を送信する (修正)
     *
     * 変更点: Permission チェックを can('receive_workflow_summary_email') に変更
     */
    public function sendWorkflowSummaryNotification(User $recipient): void
    {
        // 修正: 集約メール受信 Permission をチェック
        // can() メソッドは RoleFolderPermission を考慮しないため、
        // システム内通知の 'notify' とメール通知の 'receive_workflow_summary_email' の両方をチェックするか、
        // またはメール送信は Notification クラスの via() に任せる。
        // ここでは、そもそもこのメソッドが呼ばれるべきかを判断する意味で 'notify' をチェックしておく方が良いかもしれない。
        // => やはり via() に任せるのがシンプル。ここではカウンターチェックのみ。
        /*
        if (!$recipient->can('notify')) { // 'notify' Permission (システム内通知)
            Log::info("User {$recipient->id} does not have 'notify' permission. Skipping summary notification entirely.");
            return;
        }
        */

        $inspectionCount = $recipient->pending_inspection_count ?? 0;
        $approvalCount = $recipient->pending_approval_count ?? 0;
        $totalCount = $inspectionCount + $approvalCount;

        if ($totalCount <= 0) {
            // Log::info("User {$recipient->id} has no pending tasks, skipping summary notification.");
            return; // タスクがなければ何もしない
        }

        Log::info("Dispatching workflow summary notification for User ID: {$recipient->id}, Total: {$totalCount}");

        try {
            // WorkflowSummaryNotification をインスタンス化して送信
            // メール送信可否は via() で判断される
            Notification::send($recipient, new WorkflowSummaryNotification($inspectionCount, $approvalCount));
            Log::info("Workflow summary notification dispatched successfully.", ['recipient_id' => $recipient->id]);
        } catch (\Exception $e) {
            Log::error("Failed to dispatch workflow summary notification: " . $e->getMessage(), [
                'recipient_id' => $recipient->id, 'exception' => $e
            ]);
        }
    }

    /**
     * ユーザーが集約通知を受け取る設定か確認するヘルパー
     * (フォルダに依存しないため、shouldReceiveNotification とは別のロジック)
     */
    protected function shouldReceiveSummaryNotification(User $user, NotificationType $notificationType): bool
    {
        // ToDo: グローバルな通知設定を確認するロジックを実装する
        // 例1: User モデルに直接設定フラグを持つ
        // return $user->receives_workflow_summary;

        // 例2: RoleFolderPermission で特別な folder_id (例: 0 or null) を使う
        $userRoles = $this->userService->getAllUniqueRolesForUser($user);
        if ($userRoles->isEmpty()) {
            return false;
        }
        $roleIds = $userRoles->pluck('id')->toArray();

        return RoleFolderPermission::whereIn('role_id', $roleIds)
            // ->whereNull('folder_id') // folder_id が NULL のものをグローバル設定とみなす？
            ->where('folder_id', 0) // または特定のID (例: 0) を使う？
            ->where('notification_type_id', $notificationType->id)
            ->where('permission', FolderPermissionType::NOTIFY_ON)
            ->exists();

        // 例3: 常に True (全員に送る場合)
        // return true;
    }

}
