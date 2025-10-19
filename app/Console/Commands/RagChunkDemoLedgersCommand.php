<?php

namespace App\Console\Commands;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RagChunkDemoLedgersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rag:chunk-demo-ledgers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chunk and embed ledger entries created by DemoSeeder.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to chunk demo ledger data...');

        // 1. Find ledger IDs associated with demo data
        $demoLedgerIds = Ledger::whereHas('define', function ($query) {
            $query->where('title', 'like', '[DEMO]%');
        })->pluck('id');

        if ($demoLedgerIds->isEmpty()) {
            $this->info('No demo ledger data found to chunk.');
            return 0;
        }

        // 2. Clear existing chunks for these ledgers
        $this->info("Clearing existing chunks for {$demoLedgerIds->count()} demo ledgers.");
        DB::table('ledger_chunks')->whereIn('ledger_id', $demoLedgerIds)->delete();

        // 3. Dispatch jobs for each demo ledger
        $this->info('Dispatching chunking jobs...');
        $bar = $this->output->createProgressBar($demoLedgerIds->count());
        $bar->start();

        Ledger::findMany($demoLedgerIds)->each(function (Ledger $ledger) use ($bar) {
            ProcessLedgerForRagJob::dispatch($ledger);
            $bar->advance();
        });

        $bar->finish();
        $this->info("\nSuccessfully dispatched chunking jobs for all demo ledgers.");

        return 0;
    }
}
