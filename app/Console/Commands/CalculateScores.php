<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use App\Models\Tenant;
use App\Services\Scoring\ActivityScoreService;
use App\Services\Scoring\CompositeScoreCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * スコア計算コマンド（簡素化版）
 *
 * Phase 1: config/ledgerleap.php の設定を使用
 * ScoringConfigモデルへの依存を削除
 */
class CalculateScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scoring:calculate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update activity scores and composite scores for all ledgers for each tenant.';

    /**
     * Execute the console command.
     */
    public function handle(ActivityScoreService $activityScoreService, CompositeScoreCalculator $compositeScoreCalculator): int
    {
        $this->info('Starting score calculation process for all tenants...');
        Log::info('Starting score calculation process for all tenants...');

        $tenants = Tenant::all();
        $this->info("Found {$tenants->count()} tenants.");

        $tenants->each(function (Tenant $tenant) use ($activityScoreService, $compositeScoreCalculator) {
            tenancy()->initialize($tenant);

            $this->info("Processing tenant: {$tenant->id}");
            Log::info("Processing tenant: {$tenant->id}");

            $ledgers = Ledger::all();
            $total = $ledgers->count();

            if ($total === 0) {
                $this->info('No ledgers found for this tenant. Skipping.');
                Log::info("No ledgers found for tenant {$tenant->id}. Skipping.");

                return;
            }

            $this->info("Found {$total} ledgers to process.");

            $progressBar = $this->output->createProgressBar($total);
            $progressBar->start();

            foreach ($ledgers as $ledger) {
                // 1. 活動スコアを計算して保存
                $activityScore = $activityScoreService->calculateForLedger($ledger);
                $ledger->activity_score = $activityScore;

                // 2. 複合スコアを計算して保存（configから重み付けを取得）
                $compositeResult = $compositeScoreCalculator->calculate($ledger);
                $ledger->composite_score = $compositeResult['composite_score'];

                $ledger->save();
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->info("\nFinished processing for tenant: {$tenant->id}");
            Log::info("Finished processing for tenant: {$tenant->id}");
        });

        $this->info('Score calculation process completed for all tenants.');
        Log::info('Score calculation process completed for all tenants.');

        return Command::SUCCESS;
    }
}
