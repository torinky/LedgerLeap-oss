<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RagChunkExistingLedgersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:chunk-existing-ledgers
                            {--limit= : Maximum number of ledgers to process}
                            {--offset=0 : Number of ledgers to skip}
                            {--force : Force re-chunk all ledgers (delete existing chunks)}
                            {--only-missing : Only process ledgers without chunks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process existing ledgers to create RAG chunks (useful after model change)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('rag.enabled', false)) {
            $this->error('RAG feature is disabled. Set RAG_ENABLED=true in .env');

            return Command::FAILURE;
        }

        $this->info('RAG Existing Ledgers Chunking Tool');
        $this->newLine();

        // Get options
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $offset = (int) $this->option('offset');
        $force = $this->option('force');
        $onlyMissing = $this->option('only-missing');

        // Build query
        $query = Ledger::query();

        if ($onlyMissing) {
            // Find ledgers without any chunks
            $query->whereNotExists(function ($subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('ledger_chunks')
                    ->whereColumn('ledger_chunks.ledger_id', 'ledgers.id');
            });
            $this->info('Mode: Only processing ledgers without chunks');
        } else {
            $this->info('Mode: Processing all ledgers');
        }

        if ($force && ! $onlyMissing) {
            $this->warn('Force mode: All existing chunks will be deleted and recreated');
        }

        // Count total
        $total = $query->count();

        if ($total === 0) {
            $this->info('No ledgers to process.');

            return Command::SUCCESS;
        }

        // Apply limit and offset
        if ($offset > 0) {
            $query->skip($offset);
            $this->info("Skipping first {$offset} ledgers");
        }

        if ($limit) {
            $query->take($limit);
            $this->info("Processing up to {$limit} ledgers");
        }

        $ledgersToProcess = $query->count();

        $this->info("Total ledgers in database: {$total}");
        $this->info("Ledgers to process: {$ledgersToProcess}");
        $this->newLine();

        // Confirm
        if (! $this->confirm('Do you want to continue?', true)) {
            $this->info('Cancelled.');

            return Command::SUCCESS;
        }

        // Get current model configuration
        $activeModel = config('rag.model.active', 'unknown');
        $modelConfig = config("rag.model.available_models.{$activeModel}");
        $dimension = $modelConfig['dimension'] ?? 'unknown';

        $this->newLine();
        $this->info("Using model: {$activeModel} ({$dimension}D)");
        $this->newLine();

        // Process ledgers
        $progressBar = $this->output->createProgressBar($ledgersToProcess);
        $progressBar->start();

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        $query->chunk(100, function ($ledgers) use (&$processed, &$failed, &$skipped, $force, $progressBar) {
            foreach ($ledgers as $ledger) {
                try {
                    // Check if chunks already exist
                    $existingChunks = DB::table('ledger_chunks')
                        ->where('ledger_id', $ledger->id)
                        ->count();

                    if ($existingChunks > 0 && ! $force) {
                        $skipped++;
                        $progressBar->advance();

                        continue;
                    }

                    // Dispatch job
                    ProcessLedgerForRagJob::dispatch($ledger);
                    $processed++;
                } catch (\Exception $e) {
                    $failed++;
                    $this->newLine();
                    $this->error("Failed to process ledger {$ledger->id}: {$e->getMessage()}");
                }

                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Processing Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['Jobs Dispatched', $processed],
                ['Already Chunked (Skipped)', $skipped],
                ['Failed', $failed],
            ]
        );

        $this->newLine();
        $this->info('Jobs have been dispatched to the queue.');
        $this->info('Monitor queue progress with: ./vendor/bin/sail artisan queue:work');
        $this->newLine();

        if ($failed > 0) {
            $this->warn("⚠ {$failed} ledgers failed to process. Check logs for details.");

            return Command::FAILURE;
        }

        $this->info('✓ Command completed successfully.');

        return Command::SUCCESS;
    }
}
