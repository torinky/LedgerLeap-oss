<?php

namespace App\Livewire\Notifications;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UserNotificationList extends Component
{
    use WithPagination;

    public $selectedTab = 'notifications';

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
        $notifications = $user ? $notificationService->unreadNotificationsForUser($user)->paginate(3) : [];

        return view('livewire.notifications.user-notification-list',
            ['notifications' => $notifications])
            ->layout('layouts.app', ['title' => __('ledger.notifications')]);
    }
}
