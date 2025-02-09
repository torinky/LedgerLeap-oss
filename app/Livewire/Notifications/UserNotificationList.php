<?php

namespace App\Livewire\Notifications;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class UserNotificationList extends Component
{
    public Collection $notifications; // 型を修正

    public $selectedTab = 'notifications';

    public function mount()
    {
        $this->notifications = $this->getNotifications();
    }

    protected function getNotifications()
    {
        $user = Auth::user();
        return $user ? $user->unreadNotifications()->get() : new Collection();
    }
    // 通知を既読にするメソッド
    public function markAsRead($notificationId)
    {
//        dd("mark as read!");
        $user = Auth::user();
        if (!$user) {
            return;
        }
        // notification_user テーブルにレコードが存在するか確認
        $notificationUser = DB::table('notification_user')
            ->where('notification_id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if ($notificationUser) {
            // レコードが存在する場合は、read_at を更新
            DB::table('notification_user')
                ->where('notification_id', $notificationId)
                ->where('user_id', $user->id)
                ->update(['read_at' => now()]);
        } else {
            // レコードが存在しない場合は、新規作成
            DB::table('notification_user')->insert([
                'notification_id' => $notificationId,
                'user_id' => $user->id,
                'read_at' => now(), // 既読日時をセット
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 通知一覧を再取得
        $this->notifications = $this->getNotifications();
    }


    public function render()
    {
        return view('livewire.notifications.user-notification-list')->layout('layouts.app', ['title' => __('ledger.notifications')]);
    }
}
