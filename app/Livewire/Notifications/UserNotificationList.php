<?php

namespace App\Livewire\Notifications;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Services\NotificationService;
use Illuminate\Support\Collection;

class UserNotificationList extends Component
{
    public Collection $notifications;
    public $selectedTab = 'notifications';

    public function mount(NotificationService $notificationService)
    {
        $user = Auth::user();
        $this->notifications = $user ? $notificationService->getUnreadNotificationsForUser($user) : new Collection();
    }

    public function markAsRead(NotificationService $notificationService, string $notificationId)
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $notificationService->markNotificationAsRead($notificationId, $user);

        // 通知一覧を再取得
        $this->notifications = $notificationService->getUnreadNotificationsForUser($user);
    }

    public function render()
    {
        return view('livewire.notifications.user-notification-list')->layout('layouts.app', ['title' => __('ledger.notifications')]);
    }
}
