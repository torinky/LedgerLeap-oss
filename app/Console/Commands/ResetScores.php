<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\Tenant;
use App\Services\Scoring\ActivityScoreService;
use App\Services\Scoring\CompositeScoreCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetScores extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scoring:reset
                            {--tenant= : Specific tenant ID to reset scores for (optional)}
                            {--folder= : Specific folder ID to reset scores for (optional, recursive)}
                            {--force : Force reset without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalculate activity scores and composite scores for all ledgers from existing activity logs';

    /**
     * Execute the console command.
     */
    public function handle(
        ActivityScoreService $activityScoreService,
        CompositeScoreCalculator $compositeScoreCalculator
    ): int {
        $tenantId = $this->option('tenant');
        $folderId = $this->option('folder');
        $force = $this->option('force');

        // テナント選択
        if ($tenantId) {
            $tenants = Tenant::where('id', $tenantId)->get();
            if ($tenants->isEmpty()) {
                $this->error("Tenant with ID '{$tenantId}' not found.");

                return Command::FAILURE;
            }
            $this->info("Targeting tenant: {$tenantId}");
        } else {
            $tenants = Tenant::all();
            $this->info("Targeting all {$tenants->count()} tenants.");
        }

        // 確認プロンプト
        if (! $force) {
            $message = $this->buildConfirmationMessage($tenantId, $folderId, $tenants->count());

            if (! $this->confirm($message, false)) {
                $this->info('Recalculation cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->info('Starting score recalculation from activity logs...');
        Log::info('Starting score recalculation from activity logs...', [
            'tenant_id' => $tenantId,
            'folder_id' => $folderId,
        ]);

        $totalRecalculated = 0;

        $tenants->each(function (Tenant $tenant) use ($folderId, $activityScoreService, $compositeScoreCalculator, &$totalRecalculated) {
            tenancy()->initialize($tenant);

            $this->info("Processing tenant: {$tenant->id}");
            Log::info("Processing tenant: {$tenant->id}");

            // クエリを構築
            $query = Ledger::query();

            // フォルダ指定がある場合は絞り込み
            if ($folderId) {
                $folder = Folder::find($folderId);
                if (! $folder) {
                    $this->warn("Folder with ID '{$folderId}' not found in tenant '{$tenant->id}'. Skipping.");
                    Log::warning("Folder with ID '{$folderId}' not found in tenant '{$tenant->id}'. Skipping.");

                    return;
                }

                // 子孫フォルダを含むフォルダIDのリストを取得
                $folderIds = Folder::descendantsAndSelf($folderId)->pluck('id');
                $this->info("Recalculating scores for folder '{$folder->title}' and its descendants ({$folderIds->count()} folders)");

                // フォルダに属する台帳定義の台帳のみを対象にする
                $query->whereHas('define.folder', function ($q) use ($folderIds) {
                    $q->whereIn('id', $folderIds);
                });
            }

            $total = $query->count();
            if ($total === 0) {
                $this->info('No ledgers found for recalculation. Skipping.');
                Log::info("No ledgers found for tenant {$tenant->id}. Skipping.");

                return;
            }

            $this->info("Found {$total} ledgers to recalculate.");

            // 進捗バー作成
            $progressBar = $this->output->createProgressBar($total);
            $progressBar->start();

            // チャンクごとに処理してメモリ効率を向上
            $recalculatedCount = 0;
            $query->chunkById(100, function ($ledgers) use ($activityScoreService, $compositeScoreCalculator, &$recalculatedCount, $progressBar) {
                foreach ($ledgers as $ledger) {
                    // 1. 活動スコアを計算
                    $activityScore = $activityScoreService->calculateForLedger($ledger);
                    $ledger->activity_score = $activityScore;

                    // 2. 複合スコアを計算
                    $compositeResult = $compositeScoreCalculator->calculate($ledger);
                    $ledger->composite_score = $compositeResult['composite_score'];

                    // saveQuietly()を使用してアクティビティログを記録せずに更新
                    $ledger->saveQuietly();
                    $recalculatedCount++;
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine();

            $this->info("Recalculated {$recalculatedCount} ledgers for tenant: {$tenant->id}");
            Log::info("Recalculated {$recalculatedCount} ledgers for tenant: {$tenant->id}");

            $totalRecalculated += $recalculatedCount;
        });

        $this->info("Score recalculation completed. Total ledgers recalculated: {$totalRecalculated}");
        Log::info("Score recalculation completed. Total ledgers recalculated: {$totalRecalculated}");

        return Command::SUCCESS;
    }

    /**
     * 確認メッセージを構築
     */
    private function buildConfirmationMessage(?string $tenantId, ?string $folderId, int $tenantCount): string
    {
        $parts = ['Are you sure you want to recalculate all scores from activity logs'];

        if ($folderId) {
            $parts[] = "for folder ID '{$folderId}' (including descendants)";
        }

        if ($tenantId) {
            $parts[] = "in tenant '{$tenantId}'";
        } else {
            $parts[] = "for ALL {$tenantCount} tenants";
        }

        return implode(' ', $parts).'?';
    }
}
