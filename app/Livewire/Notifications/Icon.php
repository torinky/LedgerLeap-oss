<?php

namespace App\Livewire\Notifications;

use App\Models\User;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Icon extends Component
{
    public $unreadCount = 0;

    public function mount()
    {
        $this->unreadCount = $this->getUnreadCount();
    }

    public function getUnreadCount()
    {
        $user = Auth::user();
        if ($user) {
            return $user->unreadNotifications()->count();
        }
        return 0;

    }

    public function render()
    {
        return view('livewire.notifications.icon');
    }
}
