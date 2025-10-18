<?php

namespace App\Observers;

use App\Jobs\ProcessLedgerForRagJob;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

class LedgerObserver
{
    /**
     * Handle the Ledger "created" event.
     */
    public function created(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            ProcessLedgerForRagJob::dispatch($ledger);
        }
    }

    /**
     * Handle the Ledger "updated" event.
     */
    public function updated(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            if ($ledger->wasChanged(['content', 'content_attached'])) {
                ProcessLedgerForRagJob::dispatch($ledger);
            }
        }
    }

    /**
     * Handle the Ledger "deleted" event.
     */
    public function deleted(Ledger $ledger): void
    {
        if (config('rag.enabled', false)) {
            DB::table('ledger_chunks')->where('ledger_id', $ledger->id)->delete();
        }
    }

    /**
     * Handle the Ledger "restored" event.
     */
    public function restored(Ledger $ledger): void
    {
        //
    }

    /**
     * Handle the Ledger "force deleted" event.
     */
    public function forceDeleted(Ledger $ledger): void
    {
        //
    }
}