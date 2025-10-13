<?php

namespace App\Console\Commands;

use App\Models\Folder;
use Illuminate\Console\Command;

class AssignTenantIdToFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledgerleap:assign-tenant-id-to-folders {defaultTenantId?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assigns tenant_id to Folder models that have null tenant_id.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Assigning tenant_id to Folder models...');

        $defaultTenantId = $this->argument('defaultTenantId');

        if (! $defaultTenantId) {
            $this->warn('No default tenant ID provided. Please specify one as an argument (e.g., php artisan ledgerleap:assign-tenant-id-to-folders testa).');

            return Command::FAILURE;
        }

        $folders = Folder::withoutTenancy()->whereNull('tenant_id')->get();

        if ($folders->isEmpty()) {
            $this->info('No Folder models with null tenant_id found.');

            return Command::SUCCESS;
        }

        $this->withProgressBar($folders, function ($folder) use ($defaultTenantId) {
            $assignedTenantId = null;

            // 親フォルダが存在し、かつtenant_idが設定されている場合、親のtenant_idを継承
            if ($folder->parent_id) {
                $parentFolder = Folder::withoutTenancy()->find($folder->parent_id);
                if ($parentFolder && $parentFolder->tenant_id) {
                    $assignedTenantId = $parentFolder->tenant_id;
                }
            }

            // 親から継承できなかった場合、またはルートフォルダの場合、デフォルトテナントIDを使用
            if (! $assignedTenantId) {
                $assignedTenantId = $defaultTenantId;
            }

            $folder->tenant_id = $assignedTenantId;
            $folder->save();
            $this->comment("Assigned tenant_id '{$assignedTenantId}' to Folder ID: {$folder->id} (Title: {$folder->title})");
        });

        $this->info("\nFinished assigning tenant_id to Folder models.");

        return Command::SUCCESS;
    }
}
