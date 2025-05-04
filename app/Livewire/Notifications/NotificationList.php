<?php

namespace App\Livewire\Notifications;

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

    public int $totalNotifications = 0; // <<<--- 追加: 合計件数用プロパティ

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
            'changes' => $payload['changes'] ?? null,
            'timestamp' => $notification->created_at->diffForHumans(),
            'is_unread' => $notification->unread(),
        ];

        $notificationClassName = $notification->type;
        $causerName = $payload['causer_name'] ?? null;
        $subjectType = $payload['subject_type'] ?? null;
        $subjectId = $payload['subject_id'] ?? null;
        $event = $payload['event'] ?? ($payload['notification_type_name'] ?? null);
        $routeName = $payload['route'] ?? null;
        $routeParams = $payload['route_params'] ?? []; // payload に route_params があれば使う

        // --- メッセージ生成 ---
        $subjectLabel = $subjectType ? (__('ledger.notification_types.' . $subjectType) ?? class_basename($subjectType)) : '';
        $eventLabel = $event ? __('ledger.action_' . $event) : '';
        $subjectName = '';
        if ($subjectType === 'App\\Models\\Ledger') {
            $subjectName = $payload['ledger_name'] ?? "ID:{$subjectId}";
        } elseif ($subjectId) {
            $subjectName = "{$subjectLabel} ID:{$subjectId}";
        }

        if ($notificationClassName === 'App\\Notifications\\WorkflowSummaryNotification') { // Summary 通知の場合
            $displayData['message'] = $payload['message'] ?? __('ledger.workflow.summary_notification_default'); // Summary 用メッセージ
        } else { // GenericNotification やその他の場合
            $baseMessage = $subjectName ? "{$subjectLabel}「{$subjectName}」" : "{$subjectLabel} ";
            if ($causerName) {
                $baseMessage = "<strong>{$causerName}</strong> " . __('ledger.performed_action') . " {$baseMessage}";
            }
            $displayData['message'] = $baseMessage . $eventLabel;
        }

        // --- リンク生成 ---
        if (empty($displayData['link'])) {
            if ($routeName && Route::has($routeName)) {
                // ルートパラメータ名を特定 (仮)
                $routeParamName = match ($routeName) {
                    'ledger.show' => 'ledgerId',
                    'ledgerDiff.show' => 'ledgerId', // diffId も必要なら routeParams で渡す
                    default => 'id'
                };
                // payload に route_params があればそれを使う
                if (!empty($routeParams)) {
                    $linkParams = $routeParams;
                    // subjectId がパラメータに含まれていない場合は追加する (念のため)
                    if ($subjectId && !in_array($subjectId, $linkParams) && !isset($linkParams[$routeParamName])) {
                        $linkParams[$routeParamName] = $subjectId;
                    }
                } elseif ($subjectId) {
                    $linkParams = [$routeParamName => $subjectId];
                } else {
                    $linkParams = []; // パラメータなし
                }

                try {
                    $displayData['link'] = route($routeName, $linkParams);
                } catch (\Exception $e) {
                    Log::warning("Failed to generate route '{$routeName}' for notification ID: {$notification->id}", ['params' => $linkParams, 'error' => $e->getMessage()]);
                    $displayData['link_text'] = __('ledger.link_unavailable');
                }

            } elseif ($subjectId) {
                $displayData['link'] = null;
                $displayData['link_text'] = __('ledger.link_unavailable');
            } else {
                $displayData['link'] = null; // リンクなし
            }

        }

        return $displayData;
    }
}
