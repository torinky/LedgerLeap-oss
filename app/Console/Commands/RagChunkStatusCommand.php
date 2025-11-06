<?php

namespace App\Console\Commands;

use App\Models\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RagChunkStatusCommand extends Command
{
    protected $signature = 'rag:chunk-status';

    protected $description = 'Show the status of ledger chunking for RAG.';

    public function handle()
    {
        $this->info('Checking RAG Chunking Status...');

        $totalLedgers = Ledger::count();

        if ($totalLedgers === 0) {
            $this->info('No ledgers found in the database.');

            return 0;
        }

        $chunkedLedgers = Ledger::whereHas('chunks')->count();
        $pendingLedgers = $totalLedgers - $chunkedLedgers;

        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Ledgers', $totalLedgers, '100%'],
                ['Chunked Ledgers', $chunkedLedgers,
                    round($chunkedLedgers / $totalLedgers * 100, 1).'%'],
                ['Pending Ledgers', $pendingLedgers,
                    round($pendingLedgers / $totalLedgers * 100, 1).'%'],
            ]
        );

        $totalChunks = DB::table('ledger_chunks')->count();
        $this->info("\nTotal chunks created: {$totalChunks}");

        return 0;
    }
}
