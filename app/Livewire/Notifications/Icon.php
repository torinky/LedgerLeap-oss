<?php

namespace App\Livewire\Notifications;

use App\Models\User;
use Livewire\Component;

class Icon extends Component
{
    public $unreadCount = 0;

    public function mount()
    {
//        $this->unreadCount = $this->getUnreadCount();
        // モデルを使用せず、固定値で初期化
        $this->unreadCount = 5;
    }

    public function getUnreadCount()
    {
        /** @var User $user */
        $user = auth()->user();
        if ($user) {
            // リフレッシュ時も、固定値を返す
            $this->unreadCount = 5;
//            return $user->unreadNotifications()->count();
        }
        return 0;

    }

    public function render()
    {
        return view('livewire.notifications.icon');
    }
}
