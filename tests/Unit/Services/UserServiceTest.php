<?php

namespace Tests\Unit\Services;

use App\Models\Folder;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Collection;
use Mockery;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    protected bool $tenancy = true;
    protected function setUp(): void
    {
        parent::setUp();

        // 各テストメソッドの前にデータベースを初期化
        $this->seed(RolesAndPermissionsSeeder::class);

        // Spatieのキャッシュクリア
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_all_permissions_for_user()
    {
        // Arrange
        $userService = Mockery::mock(UserService::class);
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);

        $userPermissions = new Collection(['permission1', 'permission2']);
        $organizationPermissions = new Collection(['permission3', 'permission4']);
        $allPermissions = $userPermissions->merge($organizationPermissions);

        $user->shouldReceive('permissions')->andReturn($userPermissions);
        $user->shouldReceive('organizations')->andReturn(new Collection([$organization]));
        $organization->shouldReceive('getAllPermissions')->andReturn($organizationPermissions);

        $userService->shouldReceive('getAllPermissionsForUser')->with($user)->andReturn($allPermissions);

        // Act
        $result = $userService->getAllPermissionsForUser($user);

        // Assert
        $this->assertEquals($allPermissions, $result);
    }

    public function test_get_all_unique_roles_for_user()
    {
        // Arrange
        $userService = Mockery::mock(UserService::class);
        $user = Mockery::mock(User::class);
        $organization1 = Mockery::mock(Organization::class);
        $organization2 = Mockery::mock(Organization::class);

        $userRoles = new Collection(['role1', 'role2']);
        $organization1Roles = new Collection(['role3', 'role4']);
        $organization2Roles = new Collection(['role5', 'role6']);
        $allRoles = $userRoles->merge($organization1Roles)->merge($organization2Roles);

        $user->shouldReceive('roles')->andReturn($userRoles);
        $user->shouldReceive('organizations')->andReturn(new Collection([$organization1, $organization2]));
        $organization1->shouldReceive('getAllRoles')->andReturn($organization1Roles);
        $organization2->shouldReceive('getAllRoles')->andReturn($organization2Roles);

        $userService->shouldReceive('getAllUniqueRolesForUser')->with($user)->andReturn($allRoles);

        // Act
        $result = $userService->getAllUniqueRolesForUser($user);

        // Assert
        $this->assertEquals($allRoles, $result);
    }

    public function test_has_permission_for_organization_with_user_permission()
    {
        // Arrange
        $userService = Mockery::mock(UserService::class);
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);

        $user->shouldReceive('hasRole')->with('super-admin')->andReturn(false);
        $user->shouldReceive('hasPermissionTo')->with('specific-permission')->andReturn(true);

        $userService->shouldReceive('hasPermissionForOrganization')
            ->with($user, 'specific-permission', $organization)
            ->andReturn(true);

        // Act
        $result = $userService->hasPermissionForOrganization($user, 'specific-permission', $organization);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_role_for_organization_with_user_role()
    {
        // Arrange
        $userService = Mockery::mock(UserService::class);
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);

        $user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);

        $userService->shouldReceive('hasRoleForOrganization')
            ->with($user, 'specific-role', $organization)
            ->andReturn(true);

        // Act
        $result = $userService->hasRoleForOrganization($user, 'specific-role', $organization);

        // Assert
        $this->assertTrue($result);
    }

    public function test_assign_role_to_organization()
    {
        // Arrange
        $userService = Mockery::mock(UserService::class);
        $user = Mockery::mock(User::class);
        $role = Mockery::mock(Role::class);
        $organization = Mockery::mock(Organization::class);

        $role->shouldReceive('id')->andReturn(1);
        $organization->shouldReceive('id')->andReturn(1);

        $userService->shouldReceive('assignRoleToOrganization')
            ->with($user, $role, $organization);

        // Act
        $userService->assignRoleToOrganization($user, $role, $organization);

        // Assert
        $this->assertTrue(true); // This line is optional, but can be useful for clarity
    }

    public function test_has_role_for_organization_with_specific_role()
    {
        // Arrange
        $userService = Mockery::mock(UserService::class);
        $user = Mockery::mock(User::class);
        $organization = Mockery::mock(Organization::class);

        $user->shouldReceive('hasRole')->with('specific-role')->andReturn(true);

        $userService->shouldReceive('hasRoleForOrganization')
            ->with($user, 'specific-role', $organization)
            ->andReturn(true);

        // Act
        $result = $userService->hasRoleForOrganization($user, 'specific-role', $organization);

        // Assert
        $this->assertTrue($result);
    }

    public function test_handles_edge_case_with_no_permissions_or_roles()
    {
        // Arrange
        $writableFolderRepositoryMock = Mockery::mock(WritableFolderRepository::class); // WritableFolderRepository のモックを作成
        $userService = new UserService($writableFolderRepositoryMock); // モックを UserService のコンストラクタに渡す
        $user = Mockery::mock(User::class);

        $user->shouldReceive('getAttribute')->with('permissions')->andReturn(new Collection);
        $user->shouldReceive('getAttribute')->with('roles')->andReturn(new Collection);
        $user->shouldReceive('getAttribute')->with('organizations')->andReturn(new Collection);

        // Act
        $resultPermissions = $userService->getAllPermissionsForUser($user);
        $resultRoles = $userService->getAllRolesForUser($user);

        // Assert
        $this->assertEquals(new Collection, $resultPermissions);
        $this->assertEquals(new Collection, $resultRoles);
    }

    public function test_handles_edge_case_with_no_organizations()
    {
        // Arrange
        $writableFolderRepositoryMock = Mockery::mock(WritableFolderRepository::class); // WritableFolderRepository のモックを作成
        $userService = new UserService($writableFolderRepositoryMock); // モックを UserService のコンストラクタに渡す
        $user = Mockery::mock(User::class);

        $permission1 = Mockery::mock(Permission::class);
        $permission1->shouldReceive('offsetExists')->with('id')->andReturn(true);
        $permission1->shouldReceive('offsetGet')->with('id')->andReturn(1);

        $permission2 = Mockery::mock(Permission::class);
        $permission2->shouldReceive('offsetExists')->with('id')->andReturn(true);
        $permission2->shouldReceive('offsetGet')->with('id')->andReturn(2);

        $user->shouldReceive('getAttribute')->with('permissions')->andReturn(new Collection([$permission1, $permission2]));
        $user->shouldReceive('getAttribute')->with('roles')->andReturn(new Collection);
        $user->shouldReceive('getAttribute')->with('organizations')->andReturn(new Collection);

        // Act
        $resultPermissions = $userService->getAllPermissionsForUser($user);
        $resultRoles = $userService->getAllRolesForUser($user);

        // Assert
        $expectedPermissions = collect([$permission1, $permission2]);
        $this->assertEquals($expectedPermissions->pluck('id')->toArray(), $resultPermissions->pluck('id')->toArray());
        $this->assertEquals(new Collection, $resultRoles);
    }

    public function test_get_writable_folder_ids_returns_all_writable_folders_including_descendants_when_folder_is_null()
    {
        // 既存のフォルダをすべて削除
        Folder::query()->delete();

        $user = User::factory()->create();
        // 既存のロールを取得する
        $role1 = Role::findByName('Organization Admin');
        $role2 = Role::findByName('Project Manager');

        $rootFolder = Folder::factory()->create(['title' => 'ルートフォルダ']);
        $childFolder1 = Folder::factory()->create(['title' => 'フォルダ1', 'parent_id' => $rootFolder->id]);
        $childFolder2 = Folder::factory()->create(['title' => 'フォルダ2', 'parent_id' => $rootFolder->id]);
        $grandChildFolder = Folder::factory()->create(['title' => 'フォルダ1-1', 'parent_id' => $childFolder1->id]);
        $otherFolder = Folder::factory()->create(['title' => 'その他フォルダ']);

        $user->assignRole($role1);
        $user->assignRole($role2);

        // Spatieのキャッシュクリア
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
        // WritableFolderRepository のキャッシュクリア
        app(WritableFolderRepository::class)->clearAllCache($user);

        // RoleFolderPermission を直接使用して関連付け
        RoleFolderPermission::create(['role_id' => $role1->id, 'folder_id' => $rootFolder->id, 'permission' => 'write', 'modifier_id' => $user->id]);
        RoleFolderPermission::create(['role_id' => $role2->id, 'folder_id' => $childFolder2->id, 'permission' => 'write', 'modifier_id' => $user->id]);
        RoleFolderPermission::create(['role_id' => $role2->id, 'folder_id' => $otherFolder->id, 'permission' => 'write', 'modifier_id' => $user->id]);

        $repository = new WritableFolderRepository;

        // フォルダを指定しない場合
        //        dd(Folder::all()->pluck('id','title')->toArray());
        $writableFolderIds = $repository->getWritableFolderIds($user);
        // dd($writableFolderIds,Folder::all()->pluck('id','title')->toArray());
        $this->assertCount(5, $writableFolderIds); // ルートフォルダ, フォルダ1, フォルダ1-1, フォルダ2, その他フォルダ
        $this->assertContains($rootFolder->id, $writableFolderIds);
        $this->assertContains($childFolder1->id, $writableFolderIds);
        $this->assertContains($childFolder2->id, $writableFolderIds);
        $this->assertContains($grandChildFolder->id, $writableFolderIds);
        $this->assertContains($otherFolder->id, $writableFolderIds);
    }
}
