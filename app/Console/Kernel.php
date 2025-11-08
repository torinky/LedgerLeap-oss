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

        // --- Workflow Summary Notification ---
        $schedule->command(SendWorkflowSummaryNotification::class)
            // ->hourly(); // 毎時実行
            ->daily(); // または毎日実行
        //            ->everyMinute(); // デバッグ用

        // --- Scoring System (Phase 1.5: Step 1.8) ---
        // 環境変数から頻度を取得（デフォルト: daily）
        $frequency = config('ledgerleap.scoring.schedule_frequency', 'daily');

        $command = $schedule->command('scoring:calculate');

        // 頻度に応じた設定
        match ($frequency) {
            'everyMinute' => $command->everyMinute(),           // デバッグ用のみ
            'everyFiveMinutes' => $command->everyFiveMinutes(), // 開発・デモ推奨
            'everyTenMinutes' => $command->everyTenMinutes(),   // 開発・デモ
            'hourly' => $command->hourly(),                     // アクティブな本番
            'daily' => $command->daily(),                       // 通常の本番（デフォルト）
            'weekly' => $command->weekly(),                     // 大規模環境
            default => $command->daily(),
        };

        // --- Phase5: VLM/OCR Parallel Processing Finalization ---
        $schedule->command('ledger:finalize-processing')
            ->everyMinute()                    // 毎分実行
            ->withoutOverlapping(10)           // 重複実行防止（10分タイムアウト）
            ->onOneServer()                    // 複数サーバー環境で1サーバーのみ実行
            ->runInBackground();               // バックグラウンド実行
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
