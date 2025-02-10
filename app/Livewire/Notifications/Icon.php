<?php

namespace App\Livewire\Notifications;

use Livewire\Component;
use App\Services\NotificationService;

class Icon extends Component
{
    public $unreadCount = 0;

    public function mount(NotificationService $notificationService)
    {
        $this->unreadCount = $notificationService->getUnreadNotificationCountForUser(auth()->user());
    }

    public function render()
    {
        return view('livewire.notifications.icon');
    }
}
