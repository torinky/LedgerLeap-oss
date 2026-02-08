<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Organization;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class PermissionCacheConsistencyTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private UserService $userService;
    private $viewLedgers;
    private $manageLedgers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = \App\Models\Tenant::firstOrCreate(['id' => 'test-tenant']);
        tenancy()->initialize($this->tenant);

        $this->userService = app(UserService::class);

        // 権限の作成
        $this->viewLedgers = Permission::firstOrCreate(['name' => 'view_ledgers', 'guard_name' => 'web']);
        $this->manageLedgers = Permission::firstOrCreate(['name' => 'manage_ledgers', 'guard_name' => 'web']);
    }

    protected function getTablesToTruncate(): array
    {
        return ['users', 'roles', 'organizations', 'permissions', 'model_has_roles', 'role_has_permissions', 'model_has_permissions', 'role_folder_permissions'];
    }

    #[Test]
    public function it_clears_all_permissions_cache_when_user_roles_are_updated()
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        $role->syncPermissions([$this->viewLedgers]);

        // 最初は権限なし
        expect($this->userService->hasPermission($user, 'view_ledgers'))->toBeFalse();

        // ロール付与
        $user->assignRole($role);

        // キャッシュクリアを確認
        expect($this->userService->hasPermission($user, 'view_ledgers'))->toBeTrue();

        $role->revokePermissionTo($this->viewLedgers);

        // $role->touch() がテスト環境でイベントを発火しない可能性があるため、明示的にupdateを使用
        $role->update(['description' => 'updated for test']);

        // キャッシュがクリアされ、view_ledgers がなくなることを確認
        expect($this->userService->hasPermission($user->fresh(), 'view_ledgers'))->toBeFalse();
    }

    #[Test]
    public function it_clears_all_permissions_cache_when_organization_permissions_are_updated()
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Test Org', 'org_id' => bin2hex(random_bytes(8))]);
        $user->organizations()->attach($org);

        $role = Role::firstOrCreate(['name' => 'OrgRole', 'guard_name' => 'web']);
        $role->syncPermissions([$this->viewLedgers]);
        $role->touch();
        $org->assignRole($role);

        // 組織経由で権限があることを確認
        expect($this->userService->hasPermission($user, 'view_ledgers'))->toBeTrue();

        // ロールの権限を変更
        $role->syncPermissions([$this->manageLedgers]);
        // $role->touch() の代わりに update を使用
        $role->update(['description' => 'updated for test organization']);

        // キャッシュがクリアされ、view_ledgers がなくなることを確認
        expect($this->userService->hasPermission($user->fresh(), 'view_ledgers'))->toBeFalse();
        expect($this->userService->hasPermission($user->fresh(), 'manage_ledgers'))->toBeTrue();
    }

    #[Test]
    public function it_does_not_bypass_Super_Admin_when_folder_permission_is_strictly_required()
    {
        // 以前は Super Admin ロールを持っているだけで pass していた箇所が、今は厳格にチェックされることを確認
        $user = User::factory()->create();
        $superAdminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);
        $user->assignRole($superAdminRole);

        $folder = \App\Models\Folder::create(['title' => 'Private Folder', 'creator_id' => $user->id, 'modifier_id' => $user->id]);

        // Super Admin であっても、このフォルダへの RoleFolderPermission がなければ False
        expect($this->userService->isWritableFolderForUser($user, $folder))->toBeFalse();

        // 権限を付与
        \App\Models\RoleFolderPermission::create([
            'role_id' => $superAdminRole->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::ADMIN,
        ]);

        expect($this->userService->isWritableFolderForUser($user, $folder))->toBeTrue();
    }
    #[Test]
    public function it_clears_accessible_tenants_cache_when_user_is_updated()
    {
        $tenantAccessService = app(\App\Services\TenantAccessService::class);
        $user = User::factory()->create();

        // サービスにアクセスしてキャッシュを生成
        $tenantAccessService->getAccessibleTenants($user);

        $cacheKey = "user.{$user->id}.accessible_tenants";
        expect(\Illuminate\Support\Facades\Cache::tags(['tenant_access'])->has($cacheKey))->toBeTrue();

        // ユーザーを更新（Observer経由でキャッシュクリアされるはず）
        $user->update(['name' => 'Updated Name']);

        // キャッシュが消えていることを確認
        expect(\Illuminate\Support\Facades\Cache::tags(['tenant_access'])->has($cacheKey))->toBeFalse();
    }
}


