<?php

namespace Tests\Feature;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LedgerDeletionPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $tenancy = true;

    private User $adminUser;

    private Role $adminRole;

    private Ledger $ledgerInAdminFolder;

    private Folder $adminFolder;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. 基本権限を作成
        Permission::findOrCreate('delete_ledgers', 'web');

        // 2. ロールを作成し、基本権限を付与
        $this->adminRole = Role::findOrCreate('admin-for-delete-test', 'web');
        $this->adminRole->givePermissionTo('delete_ledgers');

        // 3. ユーザーを作成し、ロールを割り当て
        $this->adminUser = User::factory()->create()->assignRole($this->adminRole);

        // 4. テナント内でフォルダと台帳を作成
        $this->tenant->run(function () {
            $this->adminFolder = Folder::factory()->create(['title' => 'Admin Folder']);
            $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $this->adminFolder->id]);
            $this->ledgerInAdminFolder = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id]);

            // 5. フォルダ権限を付与
            RoleFolderPermission::create([
                'role_id' => $this->adminRole->id,
                'folder_id' => $this->adminFolder->id,
                'permission' => FolderPermissionType::ADMIN,
                'modifier_id' => $this->adminUser->id,
            ]);
        });
    }

    #[Test]
    public function user_with_both_permissions_can_delete_ledger(): void
    {
        // 実行
        $response = $this->actingAs($this->adminUser)
            ->delete(route('ledger.destroy', ['tenant' => $this->tenant, 'ledger' => $this->ledgerInAdminFolder->id]));

        // 検証
        $response->assertStatus(302); // Redirect on success
        $this->assertDatabaseMissing('ledgers', ['id' => $this->ledgerInAdminFolder->id]);
    }

    #[Test]
    public function user_with_only_basic_permission_cannot_delete_ledger(): void
    {
        // 準備: フォルダ権限を剥奪
        RoleFolderPermission::where('folder_id', $this->adminFolder->id)->delete();

        // 実行
        $response = $this->actingAs($this->adminUser)
            ->delete(route('ledger.destroy', ['tenant' => $this->tenant, 'ledger' => $this->ledgerInAdminFolder->id]));

        // 検証
        $response->assertForbidden();
        $this->assertDatabaseHas('ledgers', ['id' => $this->ledgerInAdminFolder->id]);
    }

    #[Test]
    public function user_with_only_folder_permission_cannot_delete_ledger(): void
    {
        // 準備: 基本権限を剥奪
        $this->adminUser->removeRole($this->adminRole);
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // 実行
        $response = $this->actingAs($this->adminUser)
            ->delete(route('ledger.destroy', ['tenant' => $this->tenant, 'ledger' => $this->ledgerInAdminFolder->id]));

        // 検証
        $response->assertForbidden();
        $this->assertDatabaseHas('ledgers', ['id' => $this->ledgerInAdminFolder->id]);
    }

    #[Test]
    public function user_cannot_delete_ledger_in_another_tenant(): void
    {
        // 準備: 別のテナントを作成し、そこに台帳を作成
        $anotherTenant = \App\Models\Tenant::create(['id' => 'another-tenant']);
        $anotherLedger = $anotherTenant->run(function () {
            $folder = Folder::factory()->create();
            $define = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

            return Ledger::factory()->create(['ledger_define_id' => $define->id]);
        });

        // 実行
        $response = $this->actingAs($this->adminUser)
            ->delete(route('ledger.destroy', ['tenant' => $anotherTenant->id, 'ledger' => $anotherLedger->id]));

        // 検証
        $response->assertNotFound();
        $anotherTenant->run(function () use ($anotherLedger) {
            $this->assertDatabaseHas('ledgers', ['id' => $anotherLedger->id]);
        });
    }
}
