<?php

namespace App\Livewire\Notifications;

use App\Livewire\BaseLivewireComponent;
use App\Services\AdminAnnouncementService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;

class Icon extends BaseLivewireComponent
{
    public $unreadCount = 0;

    public $adminAnnouncementCount = 0;

    public $notificationCount = 0;

    public $pendingTaskCount = 0; // ワークフロー未処理件数

    //    public $tenantId; // ここを追加

    // NotificationService は boot でインジェクトする方がより安全
    protected NotificationService $notificationService;

    protected AdminAnnouncementService $adminAnnouncementService;

    public function boot(
        NotificationService $notificationService,
        AdminAnnouncementService $adminAnnouncementService
    ): void {
        $this->notificationService = $notificationService;
        $this->adminAnnouncementService = $adminAnnouncementService;
    }

    public function mount() // NotificationService のインジェクト削除
    {
        // mount 時にも両方の件数を取得
        $this->refreshCounts();
        //        $this->tenantId = tenant()?->id; // ここを追加
    }

    /**
     * 未読通知件数とワークフロー未処理件数を更新する
     */
    public function refreshCounts() // メソッド名変更、引数削除
    {
        if ($user = Auth::user()) { // ログインしているか確認
            $this->unreadCount = $this->notificationService->getUnreadNotificationCountForUser($user);
            $this->adminAnnouncementCount = count($this->adminAnnouncementService->notificationCenterAnnouncements());
            $this->notificationCount = $this->unreadCount + $this->adminAnnouncementCount;
            $this->pendingTaskCount = $user->pending_inspection_count
                + $user->pending_approval_count; // <<<--- 追加: User モデルから直接取得
        } else {
            $this->unreadCount = 0;
            $this->adminAnnouncementCount = 0;
            $this->notificationCount = 0;
            $this->pendingTaskCount = 0;
        }
    }

    public function render()
    {
        return view('livewire.notifications.icon');
    }
}
