<?php

namespace App\Livewire\Notifications;

use Livewire\Component;

class Settings extends Component
{
    public $notificationTypes = [];
    public $settings = [];

    public function mount()
    {
        // 固定の通知タイプデータ
        $this->notificationTypes = [
            [
                'id' => 1,
                'name' => '台帳レコード作成',
                'description' => '台帳レコードが作成されたときに通知します。',
            ],
            [
                'id' => 2,
                'name' => 'ファイルアップロード',
                'description' => 'ファイルがアップロードされたときに通知します。',
            ],
            [
                'id' => 3,
                'name' => '台帳レコード更新',
                'description' => '台帳レコードが更新されたときに通知します。',
            ],
        ];

        // すべての通知タイプを有効にする
        foreach ($this->notificationTypes as $type) {
            $this->settings[$type['id']] = true;
        }
    }

    public function render()
    {
        return view('livewire.notifications.settings')->layout('layouts.app');
    }

    public function save(): void
    {
        // 保存処理は後ほど実装
        // ここでは、フォーム送信が機能することを確認するために、
        // 何も処理を行いません。
    }
}
