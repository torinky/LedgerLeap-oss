<?php

namespace App\Console\Commands\Ledger;

use App\Models\LedgerDefine;
use App\Models\Tenant;
use Illuminate\Console\Command;

class RegenerateLedgerDefaultSort extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:regenerate-default-sort 
                            {ledger_define_id? : 特定の台帳定義ID（省略時は全件）}
                            {--force : 確認なしで実行}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'レコードの default_sort_value を再生成 (マルチテナント対応)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ledgerDefineId = $this->argument('ledger_define_id');
        $force = $this->option('force');

        if (! $ledgerDefineId && ! $force && ! $this->confirm('全台帳定義のレコードを再生成しますか？')) {
            $this->info('Cancelled.');

            return 0;
        }

        $tenants = Tenant::all();
        $this->info("Found {$tenants->count()} tenants.");

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $this->info("Processing tenant: {$tenant->id}");

            $query = LedgerDefine::query();
            if ($ledgerDefineId) {
                $query->where('id', $ledgerDefineId);
            }

            $defines = $query->get();
            if ($defines->isEmpty()) {
                $this->info('  No LedgerDefine found for this tenant. Skipping.');

                continue;
            }

            foreach ($defines as $define) {
                $this->info("  Dispatching regeneration job for LedgerDefine [{$define->id}]: {$define->name}");
                \App\Jobs\Ledger\RegenerateLedgerSortValuesJob::dispatch($define->id);
            }
        }

        $this->info('Regeneration jobs dispatched successfully.');

        return Command::SUCCESS;
    }
}
