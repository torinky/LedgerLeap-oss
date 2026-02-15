<?php

namespace Tests\Unit\Services;

use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Role;
use App\Services\PermissionService;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * PermissionService のユニットテスト
 *
 * Phase 1.3: PermissionService のテスト強化（基本的なスモークテスト）
 *
 * @see app/Services/PermissionService.php
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
    public function it_can_get_access_roles_with_permissions()
    {
        // Arrange
        $permission = Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // Act - 正しいメソッドシグネチャを使用
        $result = $this->permissionService->getAccessRolesWithPermissions(
            $ledgerDefine->id,
            'LedgerDefine'
        );

        // Assert - Collectionが返される
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
    }

    #[Test]
    public function it_can_get_access_organizations_with_permissions()
    {
        // Arrange
        $permission = Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'OrgRole', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);

        $org = Organization::create([
            'name' => 'Test Org',
            'org_id' => bin2hex(random_bytes(8)),
        ]);
        $org->assignRole($role);

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // Act - 正しいメソッドシグネチャを使用
        $result = $this->permissionService->getAccessOrganizationsWithPermissions(
            $ledgerDefine->id,
            'LedgerDefine'
        );

        // Assert - Collectionが返される
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $result);
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
}
