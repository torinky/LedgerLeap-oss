<?php

namespace App\Livewire\Notifications;

use App\Models\Role;
use Livewire\Component;
use Illuminate\Notifications\DatabaseNotification;

// 追加
use Illuminate\Support\Facades\Auth;

// 追加
use Illuminate\Support\Facades\DB;

// 追加

class Index extends Component
{
    public $notifications = [];
    public $selectedTab = 'notifications';

    public function mount()
    {
        $user = Auth::user();
        if ($user) {
            // ログインユーザーが持つロールに紐づく通知を取得
            $this->notifications = DatabaseNotification::query()
                ->whereIn('notifiable_id', $user->roles->pluck('id')) // ユーザーのロールに紐づく通知
                ->where('notifiable_type', Role::class)
                ->leftJoin('notification_user', function ($join) use ($user) {
                    $join->on('notifications.id', '=', 'notification_user.notification_id')
                        ->where('notification_user.user_id', '=', $user->id);
                })
                ->select('notifications.*', 'notification_user.read_at') // read_at を取得
                ->orderBy('notifications.created_at', 'desc')
                ->get();
        }
    }

    // 通知を既読にするメソッド
    public function markAsRead($notificationId)
    {
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
        $this->mount();
    }

    // ... (他のメソッド) ...

    public function render()
    {
        return view('livewire.notifications.index')->layout('layouts.app', ['title' => __('ledger.notifications')]);
    }
}
