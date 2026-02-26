<?php

namespace App\Console\Commands;

use App\Services\AdSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AdSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ad:sync {--dry-run : Run the sync without making changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize users and organizations from Active Directory';

    protected AdSyncService $adSyncService;

    public function __construct(AdSyncService $adSyncService)
    {
        parent::__construct();
        $this->adSyncService = $adSyncService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('Starting AD Sync (Dry Run: '.($dryRun ? 'Yes' : 'No').')...');

        try {
            $result = $this->adSyncService->sync($dryRun);

            $this->info("AD Sync completed. Synced Users: {$result['synced_users']}, Synced Organizations: {$result['synced_organizations']}");

            return 0;
        } catch (\Exception $e) {
            $this->error('AD Sync failed: '.$e->getMessage());
            Log::error('AD Sync command failed', ['exception' => $e]);

            return 1;
        }
    }
}
