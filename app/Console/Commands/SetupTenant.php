<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SetupTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-tenant {tenant_id : The ID of the tenant} {admin_email : The email of the admin user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new tenant and run all necessary setup processes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant_id');

        // テナントの存在チェック
        if (\App\Models\Tenant::find($tenantId)) {
            $this->error("Tenant with ID '{$tenantId}' already exists.");
            return 1;
        }

        $this->info("Creating tenant: {$tenantId}");

        // テナント作成
        try {
            $tenant = \App\Models\Tenant::create(['id' => $tenantId]);

            // ドメインの紐付け
            $domain = $tenantId . '.localhost';
            $tenant->domains()->create(['domain' => $domain]);

            $this->info("Tenant '{$tenantId}' created successfully.");
            $this->info("Domain '{$domain}' has been linked.");

        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
