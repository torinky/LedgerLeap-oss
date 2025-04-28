<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use App\Services\NotificationService;

class Icon extends Component
{
    public $unreadCount = 0;
    public $pendingTaskCount = 0; // ワークフロー未処理件数

    // NotificationService は boot でインジェクトする方がより安全
    protected NotificationService $notificationService;

    public function boot(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }


    public function mount() // NotificationService のインジェクト削除
    {
        // mount 時にも両方の件数を取得
        $this->refreshCounts();
    }

    /**
     * 未読通知件数とワークフロー未処理件数を更新する
     */
    public function refreshCounts() // メソッド名変更、引数削除
    {
        if ($user = Auth::user()) { // ログインしているか確認
            $this->unreadCount = $this->notificationService->getUnreadNotificationCountForUser($user);
            $this->pendingTaskCount = $user->pending_inspection_count + $user->pending_approval_count; // <<<--- 追加: User モデルから直接取得
        } else {
            $this->unreadCount = 0;
            $this->pendingTaskCount = 0;
        }
    }

    public function render()
    {
        return view('livewire.notifications.icon');
    }
}
