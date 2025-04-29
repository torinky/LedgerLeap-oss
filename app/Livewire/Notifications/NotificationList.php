<?php

namespace App\Livewire\Notifications;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
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
        $query = $user ? $notificationService->unreadNotificationsForUser($user) : null; // <<<--- ページネーション前のクエリを取得

        if ($query) {
            $this->totalNotifications = $query->count(); // <<<--- 合計件数を取得してプロパティにセット
            $notifications = $query->paginate(10); // ページネーション実行
        } else {
            $this->totalNotifications = 0;
            $notifications = collect()->paginate(10); // 空のページネーション
        }
//        dd($this->totalNotifications);
        // <<<--- 追加: 件数をイベントで親に通知 ---
        $this->dispatch('update-tab-count', tab: 'notifications', count: $this->totalNotifications);
        // --- ここまで ---

        return view('livewire.notifications.notification-list', ['notifications' => $notifications]);
    }
}
