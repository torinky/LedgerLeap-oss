<?php

namespace App\Models;

use App\Services\NotificationService;
use Spatie\Activitylog\Models\Activity;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class CustomActivity extends Activity
{
    use BelongsToTenant;

    protected static function booted()
    {
        static::created(function (CustomActivity $activity) {
            // Activity ログが保存された後に呼び出される
            // 検索履歴は通知対象外とする
            if ($activity->event === 'searched') {
                return;
            }

            //            if ($activity->subject_type === 'App\Models\Ledger') { // Ledger モデルの場合のみ通知
            app(NotificationService::class)->processActivityLog($activity);
            //            }
        });
    }
}
