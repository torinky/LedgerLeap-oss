<?php

namespace App\Notifications;

use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

class GenericNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected int $notificationTypeId;
    protected Model $subject;
    protected ?Activity $activity = null; // <<<--- Nullable に変更し、初期値を null に
    protected ?User $causer = null; // <<<--- 追加: 操作者 (Activityがない場合用)
    protected ?string $eventName = null; // <<<--- 追加: イベント名 (Activityがない場合用)
    protected ?string $comment = null; // <<<--- 追加: コメント (Activityがない場合用)
    protected array $payloadOverrides = []; // <<<--- 追加: その他の情報

    /**
     * Create a new notification instance.
     *
     * @param int $notificationTypeId
     * @param Model $subject
     * @param Activity|null $activity Activity Log オブジェクト (オプション)
     * @param User|null $causer 操作者 (Activityがない場合に指定)
     * @param string|null $eventName イベント名 (Activityがない場合に指定)
     * @param string|null $comment コメント (Activityがない場合に指定)
     * @param array $payloadOverrides その他のペイロード情報
     */
    public function __construct(
        int       $notificationTypeId,
        Model     $subject,
        ?Activity $activity = null, // <<<--- Nullable に変更し、デフォルト null
        ?User     $causer = null,
        ?string   $eventName = null,
        ?string   $comment = null,
        array     $payloadOverrides = []
    )
    {
        $this->notificationTypeId = $notificationTypeId;
        $this->subject = $subject;
        $this->activity = $activity;
        $this->causer = $causer ?? $activity?->causer; // activity があれば causer を優先
        $this->eventName = $eventName ?? $activity?->event; // activity があれば event を優先
        $this->comment = $comment; // コメントは直接受け取る
        $this->payloadOverrides = $payloadOverrides;
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

        if (!$notificationType) {
            Log::error('NotificationType not found!', ['id' => $this->notificationTypeId]);
            return [];
        }

        // --- ペイロード構築ロジック修正 ---
        $payload = [
            'notification_type_id' => $this->notificationTypeId, // タイプIDも入れておくと便利
            'notification_type_name' => $notificationType->name,
            'subject_type' => get_class($this->subject),
            'subject_id' => $this->subject->id ?? null,
            'route' => $notificationType->route,
            'causer_id' => $this->causer?->id,
            'causer_name' => $this->causer?->name,
            'event' => $this->eventName, // コンストラクタで受け取ったイベント名
            'comments' => $this->comment, // コンストラクタで受け取ったコメント
            'ledger_name' => ($this->subject instanceof \App\Models\Ledger) ? $this->subject->define?->title : null,
            'ledger_version' => ($this->subject instanceof \App\Models\Ledger) ? $this->subject->version : null,
        ];

        // もし Activity Log オブジェクトがあれば、追加情報をマージする (オプション)
        if ($this->activity) {
            $payload['activity_log_id'] = $this->activity->id;
            $payload['description'] = $this->activity->description; // 必要なら
            $payload['changes'] = $this->activity->changes()->toArray(); // 必要なら (ただし data が大きくなる)
        }

        // payloadOverrides で渡された値で上書き・追加
        $payload = array_merge($payload, $this->payloadOverrides);

        // 通知データ全体
        $notificationData = [
            // 'notifiable_type', 'notifiable_id' は Laravel がセット
            'type' => get_class($this), // Notificationクラス名
            'payload' => $payload // payload キーの中に情報をまとめる
        ];

        // notifications テーブルへのレコード追加 (ロールに対して通知を登録)
        /*        $notificationData = [
                    'notifiable_type' => get_class($notifiable), // Spatie\Permission\Models\Role
                    'notifiable_id' => $notifiable->id ?? '',
                    'type' => $notificationType->name,
                    'payload' => [
                        'subject_type' => get_class($this->subject),
                        'route' => $notificationType->route,
                        'subject_id' => $this->subject->id ?? '',
                        'causer_name' => optional($this->activity->causer)->name ?? optional($this->activity->causer)->title ?? '',
                        'event' => $this->activity->event,
                        'description' => $this->activity->description,
                        'changes' => $this->activity->changes(),
                    ],
                ];*/

        Log::info('Notification data', ['data' => $notificationData]);

        return $notificationData;

    }
}
