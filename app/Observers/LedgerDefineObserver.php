<?php

namespace App\Observers;

use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Cache;

class LedgerDefineObserver
{
    /**
     * Handle the LedgerDefine "saved" event.
     */
    public function saved(LedgerDefine $ledgerDefine): void
    {
        // column_define が変更された場合、自動リンクのキャッシュをクリア
        if ($ledgerDefine->wasChanged('column_define')) {
            Cache::tags(['auto_links'])->flush();
        }
    }

    /**
     * Handle the LedgerDefine "deleted" event.
     */
    public function deleted(LedgerDefine $ledgerDefine): void
    {
        Cache::tags(['auto_links'])->flush();
    }
}
