<?php

namespace App\Console;

use App\Console\Commands\SendWorkflowSummaryNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // --- ステップ6.5 追加 ---
        $schedule->command(SendWorkflowSummaryNotification::class)
            // ->hourly(); // 毎時実行
            ->daily(); // または毎日実行
//            ->everyMinute(); // デバッグ用
        // --- ここまで ---
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
