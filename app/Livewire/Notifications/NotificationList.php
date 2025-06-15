<?php

namespace App\Livewire\Notifications;

use App\Helpers\ActivityLogFormatter;
use App\Models\CustomActivity;
use App\Services\NotificationService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationList extends Component
{
    use WithPagination;

    public int $totalNotifications = 0; // 合計件数用プロパティ

    public function mount(NotificationService $notificationService)
    {
    }

    // 通知を既読にするメソッド (変更なし)
    public function markAsRead(NotificationService $notificationService, string $notificationId)
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $notificationService->markAsRead($user, $notificationId);

    }

    // 全ての通知を既読にする (変更なし)
    public function markAllAsRead(NotificationService $notificationService)
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }
        $notificationService->markAsRead($user);

    }

    public function render(NotificationService $notificationService)
    {
        $user = Auth::user();
        $query = $user ? $notificationService->unreadNotificationsForUser($user) : null;

        if ($query) {
            $this->totalNotifications = $query->count();
            $notifications = $query->paginate(10) // 1ページあたりの件数を調整
            ->through(function ($notification) { // 各通知データを加工
                $notification->displayData = $this->formatNotificationData($notification);
                return $notification;
            });
        } else {
            $this->totalNotifications = 0;
            $notifications = collect()->paginate(10);
        }

        $this->dispatch('update-tab-count', tab: 'notifications', count: $this->totalNotifications);

        return view('livewire.notifications.notification-list', [
            'notifications' => $notifications
        ]);
    }

    /**
     * 通知データをビュー表示用に整形するヘルパーメソッド
     *
     * @param $notification DatabaseNotification オブジェクト
     * @return array 整形済みデータの連想配列
     */
    protected function formatNotificationData($notification): array
    {
        $payload = $notification->data['payload'] ?? ($notification->data ?? []); // まずペイロードを取得
        $displayData = [
            'message' => __('ledger.notification_types.unknown'), // デフォルトメッセージ
            'link' => $payload['link'] ?? null,
            'link_text' => __('ledger.view_details'),
            'comments' => $payload['comments'] ?? null,
            'timestamp' => $notification->created_at->diffForHumans(),
            'is_unread' => $notification->unread(),
            'changes_formatted' => null,
        ];

        $notificationClassName = $notification->type;
        $causerName = $payload['causer_name'] ?? null;
        $subjectType = $payload['subject_type'] ?? null;
        $subjectId = $payload['subject_id'] ?? null;
        $event = $payload['event'] ?? ($payload['notification_type_name'] ?? null);
        $routeName = $payload['route'] ?? null;
        $routeParams = $payload['route_params'] ?? []; // payload に route_params があれば使う


        // --- Activity Log の changes 部分のフォーマット ---
        // Notification のデータに activity_log_id が含まれている場合
        if (!empty($payload['activity_log_id'])) {
            $activityLog = CustomActivity::find($payload['activity_log_id']);
            $displayData['message'] =
                "<strong>{$causerName}</strong>"
                . __('ledger.user_action_suffix')
                . ActivityLogFormatter::getSubjectNameForDisplay($activityLog ?? null)
                .__('ledger.user_object_suffix')
                . ActivityLogFormatter::getOperationDescription($activityLog ?? null);
            if (empty($displayData['link'])) {
                $displayData['link'] = ActivityLogFormatter::getSubjectDetailLink($activityLog ?? null);
            }
            $displayData['changes_formatted'] = ActivityLogFormatter::formatChanges($activityLog ?? null);
            return $displayData; // 早期リターン

        } else {
            // --- メッセージ生成 ---
            $subjectLabel = $subjectType ? (__('ledger.activity.model_name.' . strtolower(class_basename($subjectType))) ?? class_basename($subjectType)) : ''; // 翻訳キーを ledger.activity.model_name.* に変更
            $eventLabel = $event ? __('ledger.activity.event.' . $event) : ''; // 翻訳キーを ledger.activity.event.* に変更
            $subjectName = '';
            if ($subjectType === 'App\\Models\\Ledger') {
                $subjectName = $payload['ledger_name'] ?? "ID:{$subjectId}";
            } elseif ($subjectId) {
                $subjectName = "ID:{$subjectId}"; // subjectLabel をメッセージに含めるのでここでは ID のみ
            }

            if ($notificationClassName === 'App\\Notifications\\WorkflowSummaryNotification') { // Summary 通知の場合
                $displayData['message'] = $payload['message'] ?? __('ledger.workflow.summary_notification_default'); // Summary 用メッセージ
            } else { // GenericNotification やその他の場合
                $baseMessage = $subjectName ? "{$subjectLabel}「{$subjectName}」" : "{$subjectLabel} ";
               $baseMessage .=__('ledger.user_object_suffix');
                if ($causerName) {
                    // $causer_name は 'causer_name' キーで直接渡されることを想定
                    $baseMessage = "<strong>{$causerName}</strong>" . __('ledger.user_action_suffix')
                        . "{$baseMessage}"; // 自然な日本語になるよう調整
                }
                $displayData['message'] = $baseMessage . " {$eventLabel}"; // eventLabel を最後に結合
            }
        }

        return $displayData;
    }
}
