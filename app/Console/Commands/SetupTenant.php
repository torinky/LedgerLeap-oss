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
    protected $signature = 'app:setup-tenant {tenant_id : The ID of the tenant} {name : The name of the tenant} {admin_email : The email of the admin user}';

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
        $name = $this->argument('name');
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

        $this->info("Creating tenant: {$tenantId} ({$name})");
        $tenant = null; // for rollback

        try {
            // 3. テナントの作成とドメインの紐付け
            $tenant = Tenant::create(['id' => $tenantId]); // まずIDだけで作成
            $tenant->name = $name; // プロパティとして代入
            $tenant->save(); // 保存

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

            $this->info('Setting up initial data for the tenant...');

            // 1. 管理者ユーザーの検索 (中央DBのコンテキストで実行)
            $this->info("Processing admin user: {$adminEmail}");
            $user = tenancy()->central(function () use ($adminEmail) {
                return \App\Models\User::where('email', $adminEmail)->first();
            });

            if (!$user) {
                $this->error("Admin user with email '{$adminEmail}' not found in the central database. The tenant was created, but no admin was assigned.");
                return 1; // テナントは作成されたが、管理者がいない状態で終了
            }

            // 2. ルートフォルダの作成と権限付与 (テナントのコンテキストで実行)
            $rootFolder = $tenant->run(function () use ($user) {
                $this->info('Creating root folder...');
                return \App\Models\Folder::create([
                    'title' => '/',
                    'creator_id' => $user->id,
                    'modifier_id' => $user->id,
                ]);
            });

            // 中央DBのコンテキストに戻ってロールを取得し、権限を付与
            tenancy()->central(function () use ($rootFolder, $user) {
                $this->info("Granting Super Admin access to the root folder...");
                $superAdminRole = \Spatie\Permission\Models\Role::findByName('Super Admin');
                if ($superAdminRole) {
                    \App\Models\RoleFolderPermission::create([
                        'role_id' => $superAdminRole->id,
                        'folder_id' => $rootFolder->id,
                        'permission' => \App\Enums\FolderPermissionType::ADMIN,
                        'creator_id' => $user->id,
                        'modifier_id' => $user->id,
                    ]);
                }
            });

            // ユーザーにSuper Adminロールを付与 (これは中央の model_has_roles に書き込む)
            $this->info("Assigning 'Super Admin' role to user...");
            $user->assignRole('Super Admin');

            $this->info('Admin user setup completed.');

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
        $this->info("Tenant data: " . json_encode($tenant->data));

        return Command::SUCCESS;
    }
}
