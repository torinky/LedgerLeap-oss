<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\NotificationService;
// NotificationService を use
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

// Log を use

class SendWorkflowSummaryNotification extends Command
{
    protected $signature = 'workflow:send-summary';

    protected $description = 'Send summary notifications to users with pending workflow tasks.';

    // NotificationService をインジェクト
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    public function handle(): int // 戻り値の型を追加 (Laravel 9+)
    {
        $this->info('Starting to send workflow summary notifications...');
        Log::info('Workflow Summary Notification Batch Started.');

        // 未処理タスクを持つユーザーを取得 (カウンターカラムを利用)
        $usersToNotify = User::where(function ($query) {
            $query->where('pending_inspection_count', '>', 0)
                ->orWhere('pending_approval_count', '>', 0);
        })
            ->get();

        if ($usersToNotify->isEmpty()) {
            $this->info('No users with pending tasks found.');
            Log::info('Workflow Summary: No users to notify.');

            return Command::SUCCESS;
        }

        $this->info("Found {$usersToNotify->count()} users to potentially notify.");

        foreach ($usersToNotify as $user) {
            $this->info("Processing user ID: {$user->id} Name: {$user->name}");
            try {
                // NotificationService のメソッドを呼び出す
                $this->notificationService->sendWorkflowSummaryNotification($user);
            } catch (\Exception $e) {
                Log::error("Failed to send summary notification to User ID: {$user->id}. Error: ".$e->getMessage());
                $this->error("Failed for User ID: {$user->id}");
            }
        }

        $this->info('Finished sending workflow summary notifications.');
        Log::info('Workflow Summary Notification Batch Finished.');

        return Command::SUCCESS;
    }
}
