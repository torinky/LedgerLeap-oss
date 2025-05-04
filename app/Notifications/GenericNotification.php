<?php

namespace App\Notifications;

use App\Mail\WorkflowActionMail;
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
     * @param User $notifiable User オブジェクトを受け取る想定
     * @return array
     */
    public function via(object $notifiable): array
    {
        $channels = ['database']; // デフォルトは database

        // $notifiable が User インスタンスか確認
        if (!$notifiable instanceof User) {
            Log::warning("via called with non-User notifiable.", ['notifiable_type' => get_class($notifiable)]);
            return $channels;
        }

        // --- メール送信条件を修正 ---
        $notificationType = NotificationType::find($this->notificationTypeId);

        // 1. ワークフロー関連の通知タイプか？ (NotificationType の name で判定)
        //    (例: 'status_returned_to_draft', 'approved', 'inspection_requested', ...)
        $isWorkflowActionNotification = $notificationType && in_array($notificationType->name, [
                'status_returned_to_draft',
                'approved',
                'inspection_requested',
                'approval_requested',
                'inspection_completed'
                // 必要に応じて他のワークフロー通知タイプを追加
            ]);

        // 2. ユーザーが個別アクションメールの受信権限を持っているか？
        $canReceiveActionEmail = $notifiable->can('receive_workflow_action_email');

        // ワークフロー関連通知であり、かつメール受信権限があれば mail チャネルを追加
        if ($isWorkflowActionNotification && $canReceiveActionEmail) {
            $channels[] = 'mail';
        }
        // --- ここまで修正 ---

        Log::info("Notification channels for User ID: {$notifiable->id}, Type ID: {$this->notificationTypeId}", ['channels' => $channels]);
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     * (変更なし - via() で制御するため、常に Mailable を返す想定で良い)
     *
     * @param User $notifiable User オブジェクトを受け取る想定
     * @return WorkflowActionMail|null
     */
    public function toMail(object $notifiable): ?WorkflowActionMail
    {
        if (!$notifiable instanceof User) {
            Log::warning("toMail called with non-User notifiable.", ['notifiable_type' => get_class($notifiable)]);
            return null;
        }

        $notificationType = NotificationType::find($this->notificationTypeId);

        // subject は Mailable 内で適切に処理される想定 (LedgerDiff 以外の場合も考慮)
        // Mailable 側で subject の型チェックを行う方が良いかもしれない
        // if (!$this->subject instanceof \App\Models\LedgerDiff || !$notificationType) {
        //      Log::warning("Cannot send mail for GenericNotification.", ['subject_type' => get_class($this->subject), 'notification_type_id' => $this->notificationTypeId]);
        //      return null;
        // }

        Log::info("Generating WorkflowActionMail for User ID: {$notifiable->id}, Type ID: {$this->notificationTypeId}");

        // WorkflowActionMail インスタンスを生成して返す
        // Mailable のコンストラクタや configureMailContent で subject の型に応じた処理を行う
        return (new WorkflowActionMail(
            $notificationType, // NotificationType を渡す
            $this->subject,    // Model (LedgerDiff) を渡す
            $this->causer,
            $this->comment
        ))->to($notifiable->email);
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
