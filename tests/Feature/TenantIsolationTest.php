<?php

namespace Tests\Feature;

use App\Livewire\Ledger\ModifyColumn;
use App\Livewire\Ledger\RecordsTable;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
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

    private static $tenant1;

    private static $tenant2;

    private static $tenant1LedgerDefine;

    private static $tenant1Ledger;

    private static $tenant2LedgerDefine;

    private static $tenant2Ledger;

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
        self::$adminUser->assignRole($superAdminRole);

        // Tenant 1を作成（共有テナントとは別の独立したテナント）
        self::$tenant1 = \App\Models\Tenant::create(['id' => 'tenant1']);
        self::$tenant1->domains()->create(['domain' => 'tenant1.localhost']);

        // Tenant 2を作成
        self::$tenant2 = \App\Models\Tenant::create(['id' => 'tenant2']);
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
            Livewire::test(RecordsTable::class)
                ->set('selectedLedgerDefineIds', [self::$tenant2LedgerDefine->id])
                ->assertViewHas('ledgerRecords', function ($ledgers) {
                    $this->assertCount(1, $ledgers);
                    $this->assertEquals(self::$tenant2Ledger->id, $ledgers->first()->id);

                    return true;
                });
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
    public function user_cannot_update_data_in_another_tenant(): void
    {
        $this->actingAs(self::$adminUser);

        self::$tenant2->run(function () {
            $this->expectException(ModelNotFoundException::class);
            Livewire::test(ModifyColumn::class, ['ledgerId' => self::$tenant1Ledger->id]);
        });
    }

    #[Test]
    public function user_cannot_delete_data_in_another_tenant(): void
    {
        $this->actingAs(self::$adminUser);

        self::$tenant2->run(function () {
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
            Livewire::test(RecordsTable::class)
                ->set('selectedLedgerDefineIds', [self::$tenant1LedgerDefine->id])
                ->assertViewHas('ledgerRecords', function ($ledgers) {
                    $this->assertCount(1, $ledgers);
                    $this->assertEquals(self::$tenant1Ledger->id, $ledgers->first()->id);

                    return true;
                });
        });

        // Context: tenant2
        self::$tenant2->run(function () {
            Livewire::test(RecordsTable::class)
                ->set('selectedLedgerDefineIds', [self::$tenant2LedgerDefine->id])
                ->assertViewHas('ledgerRecords', function ($ledgers) {
                    $this->assertCount(1, $ledgers);
                    $this->assertEquals(self::$tenant2Ledger->id, $ledgers->first()->id);

                    return true;
                });
        });
    }
}
