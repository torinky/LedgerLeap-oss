<?php

namespace App\Listeners;

use App\Models\NotificationType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Ledger;
use Illuminate\Support\Facades\App;

class ProcessActivityLog implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     *
     * @param Ledger $ledger
     * @return void
     */
    public function handle(Ledger $ledger) // Activity ではなく Ledger を受け取る
    {
        // Seeder 実行中 (または Artisan コマンド実行中) は処理をスキップ
        if (env('SEEDING') === true) {
            return;
        }

        // activitylog を取得 (直近の1件のみ)
        $activity = $ledger->activities()->latest()->first();

        if (!$activity) {
            return;
        }


        $notificationType = NotificationType::where('name', 'ledger_updated')->first();
        $allUsersRole = Role::where('name', 'All Users')->first();

        if (!$notificationType || !$allUsersRole) {
            return; // 必要な情報がなければ処理を中断
        }

        // 全ユーザーに通知を送信
        Notification::send($allUsersRole, new GenericNotification(
            $notificationType->id,
            $ledger, // Ledger モデル
            [
                'causer_id' => $activity->causer_id,
                'causer_name' => optional($activity->causer)->name,
                'event' => $activity->event,
            ]
        ));
    }
}
