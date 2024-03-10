<?php

namespace App\Jobs\Ledger;

use App\Models\AttachedFile;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AttachedFileScanJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $ledgerRecordId;

    /**
     * Create a new job instance.
     */
    public function __construct($ledgerRecordId)
    {
        $this->ledgerRecordId = $ledgerRecordId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $targetFiles = AttachedFile::where('ledger_id', $this->ledgerRecordId)->get();
        dd($targetFiles);
    }
}
