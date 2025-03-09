<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

class UserActivityLog extends Component
{
    use WithPagination;

    /*    public $activityLog = [];

        public function mount()
        {
            // 固定のアクションログデータ
            $this->activityLog = [
                [
                    'id' => 1,
                    'causer' => 'ユーザーA',
                    'description' => '台帳レコード「テスト台帳」を作成しました。',
                    'created_at' => now()->subMinutes(5),
                ],
                [
                    'id' => 2,
                    'causer' => 'ユーザーB',
                    'description' => 'ファイル「資料.pdf」をアップロードしました。',
                    'created_at' => now()->subMinutes(10),
                ],
                [
                    'id' => 3,
                    'causer' => 'ユーザーC',
                    'description' => '台帳レコード「サンプル台帳」を更新しました。',
                    'created_at' => now()->subMinutes(15),
                ],
            ];
        }

        public function render()
        {
            return view('livewire.user-activity-log')->layout('layouts.app');
        }*/
    public function render()
    {
        $activities = Activity::orderBy('created_at', 'desc')->paginate(10);

        //        dd($activities);
        return view('livewire.user-activity-log', ['activities' => $activities])
            ->layout('layouts.app');
    }
}
