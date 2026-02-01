<?php

namespace App\Jobs\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegenerateLedgerSortValuesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ledgerDefineId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $ledgerDefineId)
    {
        $this->ledgerDefineId = $ledgerDefineId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $define = LedgerDefine::find($this->ledgerDefineId);
        if (!$define) {
            return;
        }

        // チャンク処理で全件更新 (1000件推奨)
        Ledger::where('ledger_define_id', $this->ledgerDefineId)
            ->chunkById(1000, function ($ledgers) use ($define) {
                foreach ($ledgers as $ledger) {
                    // generateDefaultSortValue() のためにリレーションをセット
                    $ledger->setRelation('define', $define);
                    // 保存（Observer経由で生成・保存される）
                    $ledger->save();
                }
            });
    }
}
