<?php

namespace App\Console\Commands;

use Stancl\Tenancy\Database\Models\Domain;
use App\Models\Tenant;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

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
     * @throws Exception
     */
    public function handle(): int
    {
        $tenantId = $this->argument('tenant_id');
        $adminEmail = $this->argument('admin_email');
        $domain = $tenantId . '.localhost';

        // 1. テナントIDの存在チェック
        if (Tenant::find($tenantId)) {
            $this->error("Tenant with ID '{$tenantId}' already exists.");
            return 1;
        }

        // 2. ドメインの存在チェック
        if (Domain::where('domain', $domain)->exists()) {
            $this->error("Domain '{$domain}' already exists.");
            return 1;
        }

        $this->info("Creating tenant: {$tenantId}");
        $tenant = null; // for rollback

        try {
            // 3. テナントの作成とドメインの紐付け
            $tenant = Tenant::create(['id' => $tenantId]);
            $tenant->domains()->create(['domain' => $domain]);
            $this->info("Tenant '{$tenantId}' and domain '{$domain}' created successfully.");

            // 4. このテナントのコンテキストで後続処理を実行
            tenancy()->initialize($tenant);
            $this->info("Tenancy initialized for '{$tenantId}'.");

            // 5. マイグレーションとシーディングの実行
            $this->info("Running migrations...");
            Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
            $this->info("Migrations completed.");

            $this->info("Running seeding...");
            activity()->disableLogging();
            Artisan::call('tenants:seed', [
                '--tenants' => [$tenant->id],
                '--class' => 'DatabaseSeeder',
                '--force' => true
            ]);
            activity()->enableLogging();
            $this->info("Seeding completed.");

        } catch (Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            // ロールバック処理
            if ($tenant) {
                $tenant->delete();
                $this->warn("Tenant '{$tenantId}' has been rolled back.");
            }
            return 1;
        }

        $this->info("All setup processes for tenant '{$tenantId}' completed successfully.");

        return Command::SUCCESS;
    }
}
