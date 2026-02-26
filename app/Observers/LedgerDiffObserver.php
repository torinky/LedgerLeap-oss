<?php

namespace App\Observers;

use App\Models\LedgerDiff;

class LedgerDiffObserver
{
    /**
     * Handle the LedgerDiff "created" event.
     */
    public function created(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "updated" event.
     */
    public function updated(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "deleted" event.
     */
    public function deleted(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "restored" event.
     */
    public function restored(LedgerDiff $ledgerDiff): void
    {
        //
    }

    /**
     * Handle the LedgerDiff "force deleted" event.
     */
    public function forceDeleted(LedgerDiff $ledgerDiff): void
    {
        //
    }
}
