<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RagBenchmarkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:benchmark 
                            {--l|ledgers=10 : The number of ledgers to create and process}
                            {--s|content-size=2000 : The size of the content for each ledger}
                            {--sync : Whether to dispatch the job synchronously}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Benchmark the RAG ledger processing job.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ledgerCount = (int) $this->option('ledgers');
        $contentSize = (int) $this->option('content-size');
        $isSync = $this->option('sync');

        $this->info('Starting RAG Benchmark...');
        $this->info('---------------------------');
        $this->info("Ledgers to process: {$ledgerCount}");
        $this->info("Content size per ledger: {$contentSize} chars");
        $this->info('Dispatch mode: '.($isSync ? 'Synchronous' : 'Asynchronous'));
        $this->info('---------------------------');

        // Prepare necessary data
        $user = User::first();
        if (! $user) {
            $this->error('No user found. Please run database seeders first.');

            return 1;
        }

        $folder = Folder::first();
        if (! $folder) {
            $this->error('No folder found. Please run database seeders first.');

            return 1;
        }

        $ledgerDefine = LedgerDefine::first();
        if (! $ledgerDefine) {
            $this->error('No ledger define found. Please run database seeders first.');

            return 1;
        }

        $this->info("Using Ledger Define: #{$ledgerDefine->id} and User: #{$user->id}");

        $startTime = microtime(true);

        $bar = $this->output->createProgressBar($ledgerCount);
        $bar->start();

        for ($i = 0; $i < $ledgerCount; $i++) {
            $ledger = Ledger::factory()->create([
                'ledger_define_id' => $ledgerDefine->id,
                'creator_id' => $user->id,
                'content' => [
                    'title' => 'Benchmark Ledger '.($i + 1),
                    'body' => Str::random($contentSize),
                ],
                'content_attached' => Str::random($contentSize / 2),
            ]);

            if ($isSync) {
                ProcessLedgerForRagJob::dispatchSync($ledger);
            } else {
                ProcessLedgerForRagJob::dispatch($ledger);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->info('Benchmark finished.');
        $this->info('-------------------');
        $this->info('Total time: '.round($duration, 2).' seconds');
        $this->info('Average time per ledger: '.round($duration / $ledgerCount, 2).' seconds');

        if (! $isSync) {
            $this->warn('Jobs were dispatched asynchronously. The total time reflects dispatch time only.');
            $this->warn('Monitor your queue worker and logs to see the actual processing time.');
        }

        return 0;
    }
}
