<?php

namespace App\Models;

use App\Services\NotificationService;
use Spatie\Activitylog\Models\Activity ;

class CustomActivity extends Activity
{
    use \Stancl\Tenancy\Database\Concerns\BelongsToTenant;
    protected static function booted()
    {
        static::created(function (CustomActivity $activity) {
            // Activity ログが保存された後に呼び出される
            //            if ($activity->subject_type === 'App\Models\Ledger') { // Ledger モデルの場合のみ通知
            app(NotificationService::class)->processActivityLog($activity);
            //            }
        });
    }
}
