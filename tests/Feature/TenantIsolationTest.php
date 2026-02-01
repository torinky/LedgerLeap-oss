<?php

namespace Tests\Feature;

use App\Livewire\Ledger\IndexManager;
use App\Livewire\Ledger\ModifyColumn;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private static User $adminUser;

    private static User $tenant2User;

    private static Tenant $tenant1;

    private static Tenant $tenant2;

    private static LedgerDefine $tenant1LedgerDefine;

    private static Ledger $tenant1Ledger;

    private static LedgerDefine $tenant2LedgerDefine;

    private static Ledger $tenant2Ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        Notification::fake();
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * クラス全体で共有するテストデータを作成
     * 2つの独立したテナントを作成し、それぞれにデータを設定
     */
    protected function createSharedData(): void
    {
        // Admin userを作成
        self::$adminUser = User::factory()->create(['email' => 'admin@example.com', 'password' => bcrypt('password')]);

        // Super Adminロールを作成して付与
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        // Super Adminに必要な全権限を明示的に定義・作成し、ロールに付与する
        // テスト環境ではシーダーが走らないため、ここで必要な権限をセットアップする
        $allPermissionNames = [
            'view_users', 'create_users', 'update_users', 'delete_users', 'manage_users',
            'view_organizations', 'create_organizations', 'update_organizations', 'delete_organizations', 'manage_organizations',
            'view_roles', 'create_roles', 'update_roles', 'delete_roles', 'restore_roles', 'force_delete_roles',
            'view_permissions', 'create_permissions', 'update_permissions', 'delete_permissions', 'manage_permissions',
            'view_folder_permissions', 'create_folder_permissions', 'update_folder_permissions', 'delete_folder_permissions',
            'view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers',
            'view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines', 'delete_ledger_defines', 'restore_ledger_defines', 'force_delete_ledger_defines',
            'view_folders', 'create_folders', 'update_folders', 'delete_folders', 'restore_folders', 'force_delete_folders',
            'manage_auto_links',
            'notify',
            'view_activity_logs',
            'receive_workflow_summary_email',
            'receive_workflow_action_email',
        ];

        foreach ($allPermissionNames as $permissionName) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 作成した全ての権限をSuper Adminロールに同期
        $superAdminRole->syncPermissions($allPermissionNames);
        self::$adminUser->assignRole($superAdminRole);

        // Tenant 1を作成（共有テナントとは別の独立したテナント）
        self::$tenant1 = Tenant::create(['id' => 'tenant1']);
        self::$tenant1->domains()->create(['domain' => 'tenant1.localhost']);

        // Tenant 2を作成
        self::$tenant2 = Tenant::create(['id' => 'tenant2']);
        self::$tenant2->domains()->create(['domain' => 'tenant2.localhost']);

        // Tenant 1のデータを作成
        self::$tenant1->run(function () use ($superAdminRole) {
            // テナント1のマイグレーション実行
            \Artisan::call('tenants:migrate', ['--tenants' => ['tenant1']]);

            // ルートフォルダを作成
            $folder = Folder::create([
                'title' => '/',
                'creator_id' => self::$adminUser->id,
                'modifier_id' => self::$adminUser->id,
            ]);

            // フォルダ権限を作成
            \App\Models\RoleFolderPermission::create([
                'role_id' => $superAdminRole->id,
                'folder_id' => $folder->id,
                'permission' => \App\Enums\FolderPermissionType::ADMIN,
                'creator_id' => self::$adminUser->id,
                'modifier_id' => self::$adminUser->id,
            ]);

            self::$tenant1LedgerDefine = LedgerDefine::factory()->create([
                'title' => 'Tenant 1 Definition',
                'folder_id' => $folder->id,
            ]);
            self::$tenant1Ledger = Ledger::factory()->create([
                'ledger_define_id' => self::$tenant1LedgerDefine->id,
                'content' => [0 => 'tenant1-data'],
            ]);
        });

        // Tenant 2のデータを作成
        self::$tenant2->run(function () use ($superAdminRole) {
            // テナント2のマイグレーション実行
            \Artisan::call('tenants:migrate', ['--tenants' => ['tenant2']]);

            // Tenant2 専用のユーザーとロールを作成
            self::$tenant2User = User::factory()->create(['email' => 'tenant2user@example.com', 'password' => bcrypt('password')]);
            $tenant2Role = Role::firstOrCreate(['name' => 'Tenant2User', 'guard_name' => 'web']);
            self::$tenant2User->assignRole($tenant2Role);

            // ルートフォルダを作成
            $folder = Folder::create([
                'title' => '/',
                'creator_id' => self::$adminUser->id,
                'modifier_id' => self::$adminUser->id,
            ]);

            // SuperAdmin には tenant2 のフォルダ権限も付与
            \App\Models\RoleFolderPermission::create([
                'role_id' => $superAdminRole->id,
                'folder_id' => $folder->id,
                'permission' => \App\Enums\FolderPermissionType::ADMIN,
                'creator_id' => self::$adminUser->id,
                'modifier_id' => self::$adminUser->id,
            ]);

            // Tenant2User には tenant2 のフォルダ権限のみ付与
            \App\Models\RoleFolderPermission::create([
                'role_id' => $tenant2Role->id,
                'folder_id' => $folder->id,
                'permission' => \App\Enums\FolderPermissionType::ADMIN,
                'creator_id' => self::$adminUser->id,
                'modifier_id' => self::$adminUser->id,
            ]);

            self::$tenant2LedgerDefine = LedgerDefine::factory()->create([
                'title' => 'Tenant 2 Definition',
                'folder_id' => $folder->id,
            ]);
            self::$tenant2Ledger = Ledger::factory()->create([
                'ledger_define_id' => self::$tenant2LedgerDefine->id,
                'content' => [0 => 'tenant2-data'],
            ]);
        });
    }

    #[Test]
    public function user_in_one_tenant_cannot_see_data_from_another_tenant(): void
    {
        $this->actingAs(self::$adminUser);

        self::$tenant2->run(function () {
            Livewire::test(IndexManager::class, ['defineId' => self::$tenant2LedgerDefine->id])
                ->assertSee('tenant2-data')
                ->assertDontSee('tenant1-data');
        });
    }

    #[Test]
    public function user_cannot_access_another_tenants_resource_via_direct_url(): void
    {
        $this->actingAs(self::$adminUser);

        $response = self::$tenant2->run(function () {
            return $this->get(route('ledger.show', ['tenant' => 'tenant2', 'ledgerId' => self::$tenant1Ledger->id]));
        });

        $response->assertNotFound();
    }

    #[Test]
    public function super_admin_can_switch_tenant_context_to_edit_ledger(): void
    {
        $this->actingAs(self::$adminUser);

        self::$tenant2->run(function () {
            // 例外が発生しないことを確認
            Livewire::test(ModifyColumn::class, ['ledgerId' => self::$tenant1Ledger->id]);

            // 現在のテナントが tenant1 に切り替わっていることをアサート
            $this->assertEquals('tenant1', tenancy()->tenant->id);
        });
    }

    #[Test]
    public function unauthorized_user_cannot_access_resource_in_another_tenant(): void
    {
        // tenant2 にのみ権限を持つユーザーでログイン
        $this->actingAs(self::$tenant2User);

        self::$tenant2->run(function () {
            // tenant1 のリソースにアクセスしようとすると ModelNotFoundException が発生することを期待
            $this->expectException(ModelNotFoundException::class);
            Livewire::test(ModifyColumn::class, ['ledgerId' => self::$tenant1Ledger->id]);
        });
    }

    /**
     * @Test
     *
     * @see \Tests\Feature\TenantIsolationTest::validation_prevents_creating_relations_across_tenants
     * このテストは、台帳作成時に他テナントのフォルダIDを指定できてしまう脆弱性を検証する目的だったが、
     * アプリケーションの設計上、その操作自体が不可能であることが判明したため不要となった。
     * 経緯はドキュメントに記録済み。
     * @see /docs/work/2025-09-04_tenant-isolation-test-plan.md#55-validation_prevents_creating_relations_across_tenants-に関する補足
     */
    /*
    public function validation_prevents_creating_relations_across_tenants(): void
    {
        $this->actingAs(self::$adminUser);

        self::$tenant1->run(function () {
            $this->assertTrue(true); // Dummy assertion as this test's premise was wrong.
        });
    }
    */

    #[Test]
    public function user_belonging_to_multiple_tenants_can_switch_context_and_operate_correctly(): void
    {
        $this->actingAs(self::$adminUser);

        // Context: tenant1
        self::$tenant1->run(function () {
            Livewire::test(IndexManager::class, ['defineId' => self::$tenant1LedgerDefine->id])
                ->assertSee('tenant1-data')
                ->assertDontSee('tenant2-data');
        });

        // Context: tenant2
        self::$tenant2->run(function () {
            Livewire::test(IndexManager::class, ['defineId' => self::$tenant2LedgerDefine->id])
                ->assertSee('tenant2-data')
                ->assertDontSee('tenant1-data');
        });
    }
}
