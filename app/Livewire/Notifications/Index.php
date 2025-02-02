<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class Index extends Component
{
    public $notifications = [];
    public $selectedTab = 'notifications';

    public function mount()
    {
        // 固定の通知データ
        $this->notifications = [
            [
                'id' => 1,
                'type' => '台帳レコード作成',
                'message' => 'ユーザーAが台帳レコード「テスト台帳」を作成しました。',
                'read_at' => null, // すべて未読扱い
                'created_at' => now()->subMinutes(5),
            ],
            [
                'id' => 2,
                'type' => 'ファイルアップロード',
                'message' => 'ユーザーBがファイル「資料.pdf」をアップロードしました。',
                'read_at' => null, // すべて未読扱い
                'created_at' => now()->subMinutes(10),
            ],
            [
                'id' => 3,
                'type' => '台帳レコード更新',
                'message' => 'ユーザーCが台帳レコード「サンプル台帳」を更新しました。',
                'read_at' => null, // すべて未読扱い
                'created_at' => now()->subMinutes(15),
            ],
        ];
    }

    public function render()
    {
        return view('livewire.notifications.index')->layout('layouts.app', ['title' => __('ledger.notifications')]);
    }
}
