<?php

namespace App\Observers;

use App\Jobs\Ledger\RegenerateLedgerSortValuesJob;
use App\Models\LedgerDefine;
use App\Services\Ledger\ExportCacheService;
use Illuminate\Support\Facades\Cache;

class LedgerDefineObserver
{
    /**
     * Handle the LedgerDefine "saved" event.
     */
    public function saved(LedgerDefine $ledgerDefine): void
    {
        // column_define が変更された場合
        if ($ledgerDefine->wasChanged('column_define')) {
            app(ExportCacheService::class)->clearByLedgerDefineId($ledgerDefine->id);
            Cache::tags(['auto_links'])->flush();

            // sort_indexの変更を検知
            $oldColumns = $ledgerDefine->getOriginal('column_define');
            $newColumns = $ledgerDefine->column_define;

            $oldSortMap = collect($oldColumns)->pluck('sort_index', 'id')->toArray();
            $newSortMap = collect($newColumns)->pluck('sort_index', 'id')->toArray();

            if ($oldSortMap !== $newSortMap) {
                // sort_indexが変更された場合のみ再生成
                RegenerateLedgerSortValuesJob::dispatch($ledgerDefine->id)
                    ->delay(now()->addSeconds(5)); // 連続変更対策
            }
        }
    }

    /**
     * Handle the LedgerDefine "deleted" event.
     */
    public function deleted(LedgerDefine $ledgerDefine): void
    {
        app(ExportCacheService::class)->clearByLedgerDefineId($ledgerDefine->id);
        Cache::tags(['auto_links'])->flush();
    }
}
