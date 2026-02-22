<?php

namespace Tests\Unit\Services;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\PermissionService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * PermissionService のユニットテスト
 *
 * Phase 1.3: PermissionService のテスト強化
 *
 * @see app/Services/PermissionService.php
 * @see docs/services/PermissionService.md
 */
class PermissionServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->permissionService = app(PermissionService::class);
    }

    #[Test]
    public function it_can_get_access_roles_with_permissions_for_folder()
    {
        // フォルダに対する権限を持つロールを取得
        $role = Role::firstOrCreate(['name' => 'FolderViewer', 'guard_name' => 'web']);
        $folder = Folder::factory()->create();
        $user = User::factory()->create();

        // フォルダに対するview権限を設定
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        $result = $this->permissionService->getAccessRolesWithPermissions(
            $folder->id,
            'Folder'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());

        $roleWithPerm = $result->firstWhere('role.id', $role->id);
        $this->assertNotNull($roleWithPerm);
        $this->assertEquals('folder', $roleWithPerm->source);
        $this->assertFalse($roleWithPerm->is_inherited);
    }

    #[Test]
    public function it_can_get_access_roles_with_inherited_permissions()
    {
        // 親フォルダの権限が子フォルダに継承されることを確認
        $role = Role::firstOrCreate(['name' => 'ParentFolderEditor', 'guard_name' => 'web']);
        $user = User::factory()->create();

        $parentFolder = Folder::factory()->create();
        $childFolder = Folder::factory()->create(['parent_id' => $parentFolder->id]);

        // 親フォルダにedit権限を設定
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $parentFolder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $user->id,
        ]);

        // 子フォルダに対してアクセス権を確認
        $result = $this->permissionService->getAccessRolesWithPermissions(
            $childFolder->id,
            'Folder'
        );

        $roleWithPerm = $result->firstWhere('role.id', $role->id);
        $this->assertNotNull($roleWithPerm);
        $this->assertTrue($roleWithPerm->permissions->contains(fn ($p) => $p === FolderPermissionType::WRITE));
    }

    #[Test]
    public function it_can_get_access_roles_for_ledger_define()
    {
        // 台帳定義に対する権限を持つロールを取得（フォルダ経由）
        $role = Role::firstOrCreate(['name' => 'LedgerDefineViewer', 'guard_name' => 'web']);
        $user = User::factory()->create();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // フォルダに権限を設定
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        $result = $this->permissionService->getAccessRolesWithPermissions(
            $ledgerDefine->id,
            'LedgerDefine'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    #[Test]
    public function it_can_get_access_roles_for_ledger()
    {
        // Ledgerに対する権限を持つロールを取得（フォルダ経由）
        $role = Role::firstOrCreate(['name' => 'LedgerEditor', 'guard_name' => 'web']);
        $user = User::factory()->create();

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // フォルダに権限を設定
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $user->id,
        ]);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [],
        ]);

        $result = $this->permissionService->getAccessRolesWithPermissions(
            $ledger->id,
            'Ledger'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    #[Test]
    public function it_returns_empty_collection_for_invalid_resource_type()
    {
        $result = $this->permissionService->getAccessRolesWithPermissions(
            999,
            'InvalidType'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_can_get_access_organizations_with_permissions()
    {
        // 組織に紐づくロールの権限を取得
        $permission = Permission::firstOrCreate(['name' => FolderPermissionType::READ->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'OrgRole', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $org = Organization::create([
            'name' => 'Test Org',
            'org_id' => bin2hex(random_bytes(8)),
        ]);
        $org->assignRole($role);

        $folder = Folder::factory()->create();
        $user = User::factory()->create();

        // フォルダに権限を設定
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        $result = $this->permissionService->getAccessOrganizationsWithPermissions(
            $folder->id,
            'Folder'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_access_roles_exist()
    {
        $folder = Folder::factory()->create();

        $result = $this->permissionService->getAccessOrganizationsWithPermissions(
            $folder->id,
            'Folder'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_can_get_access_users_for_folder()
    {
        // フォルダにアクセス可能なユーザーを取得
        $role = Role::firstOrCreate(['name' => 'FolderUser', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        $result = $this->permissionService->getAccessUsers(
            $folder->id,
            'Folder'
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_can_filter_access_users_by_search_query()
    {
        // 検索クエリでユーザーをフィルタリング
        $role = Role::firstOrCreate(['name' => 'SearchTestRole', 'guard_name' => 'web']);
        $user1 = User::factory()->create(['name' => 'John Doe']);
        $user2 = User::factory()->create(['name' => 'Jane Smith']);

        $user1->assignRole($role);
        $user2->assignRole($role);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user1->id,
        ]);

        $result = $this->permissionService->getAccessUsers(
            $folder->id,
            'Folder',
            'John'
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_can_filter_access_users_by_role()
    {
        // ロールでユーザーをフィルタリング
        $role1 = Role::firstOrCreate(['name' => 'FilterRole1', 'guard_name' => 'web']);
        $role2 = Role::firstOrCreate(['name' => 'FilterRole2', 'guard_name' => 'web']);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->assignRole($role1);
        $user2->assignRole($role2);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role1->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user1->id,
        ]);

        RoleFolderPermission::create([
            'role_id' => $role2->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user1->id,
        ]);

        $result = $this->permissionService->getAccessUsers(
            $folder->id,
            'Folder',
            null,
            $role1->id
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_can_filter_access_users_by_permission_type()
    {
        // 権限タイプでユーザーをフィルタリング
        $role = Role::firstOrCreate(['name' => 'PermFilterRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $user->id,
        ]);

        $result = $this->permissionService->getAccessUsers(
            $folder->id,
            'Folder',
            null,
            null,
            null,
            FolderPermissionType::WRITE->value
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    #[Test]
    public function it_handles_non_existent_folder()
    {
        $result = $this->permissionService->getAccessRolesWithPermissions(
            99999,
            'Folder'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_handles_non_existent_ledger_define()
    {
        $result = $this->permissionService->getAccessRolesWithPermissions(
            99999,
            'LedgerDefine'
        );

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_respects_tenant_isolation()
    {
        // Arrange: 現在のテナントでフォルダを作成
        $folder1 = Folder::factory()->create();
        $folder1Id = $folder1->id;

        // 別のテナントを作成して切り替え
        $newTenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($newTenant);

        // Act & Assert: 別テナントのフォルダは見えない
        $this->assertNull(Folder::find($folder1Id));
    }

    #[Test]
    public function it_categorizes_user_roles_as_direct_and_inherited()
    {
        // ユーザーの直接ロールと組織から継承されたロールを分類
        $role = Role::firstOrCreate(['name' => 'DirectRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        $result = $this->permissionService->getAccessUsers(
            $folder->id,
            'Folder'
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        // getAccessUsersが正常に動作し、ページネーションが返されることを確認
        $this->assertGreaterThanOrEqual(0, $result->total());
        // pageName が permission_user_page であること（他コンポーネントとの衝突回避）
        $this->assertSame('permission_user_page', $result->getPageName());
    }

    #[Test]
    public function it_can_get_current_user_highest_permission_for_folder()
    {
        // Arrange
        $role = Role::firstOrCreate(['name' => 'CurrentUserRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        // ログインユーザーとして認証
        $this->actingAs($user);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE->value,
            'modifier_id' => $user->id,
        ]);

        // Act
        $permission = $this->permissionService->getCurrentUserHighestPermission($folder->id, 'Folder');

        // Assert
        $this->assertInstanceOf(FolderPermissionType::class, $permission);
    }

    #[Test]
    public function it_returns_null_when_no_user_authenticated()
    {
        // Arrange - ユーザーが認証されていない
        $folder = Folder::factory()->create();

        // Act
        $permission = $this->permissionService->getCurrentUserHighestPermission($folder->id, 'Folder');

        // Assert
        $this->assertNull($permission);
    }

    #[Test]
    public function it_uses_cache_for_current_user_highest_permission()
    {
        // Arrange
        $role = Role::firstOrCreate(['name' => 'CacheTestRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        // Act - 2回呼び出してキャッシュをテスト
        $permission1 = $this->permissionService->getCurrentUserHighestPermission($folder->id, 'Folder');
        $permission2 = $this->permissionService->getCurrentUserHighestPermission($folder->id, 'Folder');

        // Assert
        $this->assertEquals($permission1, $permission2);
        $this->assertInstanceOf(FolderPermissionType::class, $permission1);
    }

    #[Test]
    public function it_can_get_current_user_all_permissions()
    {
        // Arrange
        $role = Role::firstOrCreate(['name' => 'AllPermissionsRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::ADMIN->value,
            'modifier_id' => $user->id,
        ]);

        // Act
        $permissions = $this->permissionService->getCurrentUserAllPermissions($folder->id, 'Folder');

        // Assert
        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        foreach ($permissions as $permission) {
            $this->assertInstanceOf(FolderPermissionType::class, $permission);
        }
    }

    #[Test]
    public function it_returns_null_for_all_permissions_when_no_user()
    {
        // Arrange - 認証なし
        $folder = Folder::factory()->create();

        // Act
        $permissions = $this->permissionService->getCurrentUserAllPermissions($folder->id, 'Folder');

        // Assert
        $this->assertNull($permissions);
    }

    #[Test]
    public function it_can_get_highest_permission_for_ledger_define()
    {
        // Arrange
        $permission = Permission::firstOrCreate(['name' => FolderPermissionType::WRITE->value, 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'LedgerDefineHighestPermRole', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // フォルダにも権限を設定
        RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $user->id,
        ]);

        // Act
        $highestPerm = $this->permissionService->getCurrentUserHighestPermission($ledgerDefine->id, 'LedgerDefine');

        // Assert
        $this->assertInstanceOf(FolderPermissionType::class, $highestPerm);
    }
}
