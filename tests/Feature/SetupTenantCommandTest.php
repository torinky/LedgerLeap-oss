<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Stancl\Tenancy\Database\Models\Tenant;
use Tests\TestCase;

class SetupTenantCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テストに必要なロールとユーザーを作成
        // RolesAndPermissionsSeeder を実行すると Super Admin ロールが作成される
        Artisan::call('db:seed', ['--class' => 'Database\Seeders\RolesAndPermissionsSeeder']);

        // super_admin@ll.com ユーザーを作成
        User::updateOrCreate(
            ['email' => 'super_admin@ll.com'],
            [
                'name' => 'super_admin',
                'password' => bcrypt('password'), // 適当なパスワード
            ]
        );
    }

    #[test]
    public function test_setup_tenant_command_successfully_creates_tenant_and_assigns_admin(): void
    {
        $tenantId = 'test-feature-tenant';
        $tenantName = 'Test Feature Tenant';
        $adminEmail = 'super_admin@ll.com';

        // コマンドを実行
        $this->artisan('app:setup-tenant', [
            'tenant_id' => $tenantId,
            'name' => $tenantName,
            'admin_email' => $adminEmail,
        ])->assertExitCode(0); // コマンドが成功したことをアサート

        // テナントが作成され、プロパティとして名前が保存されていることを確認
        $tenant = Tenant::find($tenantId);
        $this->assertNotNull($tenant);
        $this->assertEquals($tenantName, $tenant->name);

        $this->assertDatabaseHas('domains', ['tenant_id' => $tenantId, 'domain' => "{$tenantId}.localhost"]);

        // ロール付与を確認
        $user = User::where('email', $adminEmail)->first();
        $superAdminRole = Role::where('name', 'Super Admin')->first();
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $superAdminRole->id,
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);

        // ルートフォルダが作成されたことをテナントDBで確認
        tenancy()->find($tenantId)->run(function () use ($user, $superAdminRole) {
            $rootFolder = Folder::where('title', '/')->first();
            $this->assertNotNull($rootFolder);
            $this->assertEquals($user->id, $rootFolder->creator_id);
            $this->assertEquals($user->id, $rootFolder->modifier_id);

            // ルートフォルダとSuper Adminロールの権限紐付けを確認
            $this->assertDatabaseHas('role_folder_permissions', [
                'role_id' => $superAdminRole->id,
                'folder_id' => $rootFolder->id,
                'permission' => \App\Enums\FolderPermissionType::ADMIN->value,
            ]);
        });
    }

    #[test]
    public function test_setup_tenant_command_fails_if_tenant_already_exists(): void
    {
        $tenantId = 'existing-tenant';
        $adminEmail = 'super_admin@ll.com';

        // 事前にテナントを作成
        Tenant::create(['id' => $tenantId]);

        // コマンドを実行 (重複)
        $this->artisan('app:setup-tenant', [
            'tenant_id' => $tenantId,
            'name' => 'Existing Tenant',
            'admin_email' => $adminEmail,
        ])
            ->assertExitCode(1) // コマンドが失敗したことをアサート
            ->expectsOutput("Tenant with ID '{$tenantId}' already exists."); // エラーメッセージをアサート
    }

    #[test]
    public function test_setup_tenant_command_fails_if_admin_user_not_found(): void
    {
        $tenantId = 'new-tenant-no-admin';
        $adminEmail = 'not_exists@example.com';

        // コマンドを実行
        $this->artisan('app:setup-tenant', [
            'tenant_id' => $tenantId,
            'name' => 'New Tenant',
            'admin_email' => $adminEmail,
        ])
            ->assertExitCode(1) // コマンドが失敗したことをアサート
            ->expectsOutput("Admin user with email '{$adminEmail}' not found in the central database. The tenant was created, but no admin was assigned."); // エラーメッセージをアサート

        // テナントが作成されたが、ロールバックされていないことを確認 (エラーメッセージの通り)
        $this->assertDatabaseHas('tenants', ['id' => $tenantId]);
    }

    #[test]
    public function test_tenant_seeding_creates_correct_folder_structure_and_ledger_defines(): void
    {
        $tenantId = 'test-seeding-tenant';
        $tenantName = 'Test Seeding Tenant';
        $adminEmail = 'super_admin@ll.com';

        // コマンドを実行
        $this->artisan('app:setup-tenant', [
            'tenant_id' => $tenantId,
            'name' => $tenantName,
            'admin_email' => $adminEmail,
        ])->assertExitCode(0);

        // テナントのコンテキストでDBを検証
        tenancy()->find($tenantId)->run(function () {
            // 1. フォルダ構造の検証
            $totalFolders = \App\Models\Folder::count();
            $this->assertEquals(12, $totalFolders, 'FolderSeeder should create exactly 12 folders.');

            $rootFolder = \App\Models\Folder::whereIsRoot()->first();
            $this->assertNotNull($rootFolder, 'A root folder should exist.');
            $this->assertEquals('/', $rootFolder->title, 'The root folder title should be "/".');

            // 子フォルダの数を確認
            $this->assertCount(4, $rootFolder->children, 'Root folder should have 4 direct children.');

            // 特定の親子関係を確認
            $sub1 = \App\Models\Folder::where('title', 'Subfolder 1')->first();
            $this->assertTrue($sub1->isDescendantOf($rootFolder), 'Subfolder 1 should be a descendant of the root.');

            // 2. LedgerDefine の検証
            $ledgerDefines = \App\Models\LedgerDefine::all();
            $this->assertCount(50, $ledgerDefines, '50 LedgerDefines should be created.');

            $folderIds = \App\Models\Folder::pluck('id');

            foreach ($ledgerDefines as $define) {
                $this->assertNotNull($define->folder_id, 'LedgerDefine folder_id should not be null.');
                $this->assertTrue($folderIds->contains($define->folder_id), "LedgerDefine folder_id should belong to an existing folder. Failed for LedgerDefine ID: {$define->id}");
            }
        });
    }
}
