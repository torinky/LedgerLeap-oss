<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Console\Command;

class AssignTenantIdToLedgerDefines extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledgerleap:assign-tenant-id-to-ledger-defines';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assigns tenant_id to LedgerDefine models that have null tenant_id.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Assigning tenant_id to LedgerDefine models...');

        $ledgerDefines = LedgerDefine::withoutTenancy()->whereNull('tenant_id')->get();

        if ($ledgerDefines->isEmpty()) {
            $this->info('No LedgerDefine models with null tenant_id found.');

            return Command::SUCCESS;
        }

        $this->withProgressBar($ledgerDefines, function ($ledgerDefine) {
            // フォルダが存在しない場合はスキップ
            if (! $ledgerDefine->folder_id) {
                $this->warn("Skipping LedgerDefine ID: {$ledgerDefine->id} - folder_id is null.");

                return;
            }

            // フォルダを取得 (テナントスコープを無視)
            $folder = Folder::withoutTenancy()->find($ledgerDefine->folder_id);

            if ($folder && $folder->tenant_id) {
                $ledgerDefine->tenant_id = $folder->tenant_id;
                $ledgerDefine->save();
                $this->comment("Assigned tenant_id '{$folder->tenant_id}' to LedgerDefine ID: {$ledgerDefine->id}");
            } else {
                $this->warn("Could not find Folder for LedgerDefine ID: {$ledgerDefine->id} or Folder has no tenant_id.");
            }
        });

        $this->info("\nFinished assigning tenant_id to LedgerDefine models.");

        return Command::SUCCESS;
    }
}
