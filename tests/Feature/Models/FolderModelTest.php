<?php

namespace Tests\Feature\Models;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Folder::class)]
class FolderModelTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private Folder $folder;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->seed(RolesAndPermissionsSeeder::class);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->folder = Folder::factory()->create();
        $this->role = Role::create(['name' => 'folder-role-'.uniqid()]);
    }

    // ----------------------------------------------------------------
    // 基本属性
    // ----------------------------------------------------------------

    public function test_get_name_attribute_returns_title(): void
    {
        $this->assertEquals($this->folder->title, $this->folder->name);
    }

    public function test_get_display_title_attribute(): void
    {
        $this->assertIsString($this->folder->display_title);
    }

    public function test_get_tree_label_attribute(): void
    {
        $this->assertIsString($this->folder->tree_label);
    }

    // ----------------------------------------------------------------
    // リレーション
    // ----------------------------------------------------------------

    public function test_ledger_defines_relation(): void
    {
        LedgerDefine::factory()->create(['folder_id' => $this->folder->id]);
        $this->assertGreaterThan(0, $this->folder->ledgerDefines()->count());
    }

    public function test_creator_relation(): void
    {
        $this->assertInstanceOf(User::class, $this->folder->creator);
    }

    public function test_modifier_relation(): void
    {
        $this->assertInstanceOf(User::class, $this->folder->modifier);
    }

    public function test_folders_relation_returns_children(): void
    {
        $child = Folder::factory()->create(['parent_id' => $this->folder->id]);
        $this->assertTrue($this->folder->folders->contains('id', $child->id));
    }

    public function test_role_folder_permissions_relation(): void
    {
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
        ]);

        $this->assertGreaterThan(0, $this->folder->roleFolderPermissions()->count());
    }

    // ----------------------------------------------------------------
    // getAllRoles / roles
    // ----------------------------------------------------------------

    public function test_get_all_roles_returns_empty_for_new_folder(): void
    {
        // 新規作成直後は roles なし
        $freshFolder = Folder::factory()->create();
        $this->assertCount(0, $freshFolder->getAllRoles());
    }

    public function test_roles_relation_is_accessible(): void
    {
        // roles() リレーションが呼び出し可能なこと
        $this->assertNotNull($this->folder->roles());
    }

    // ----------------------------------------------------------------
    // hasDirectPermission / getDirectPermissions
    // ----------------------------------------------------------------

    public function test_has_direct_permission_returns_true(): void
    {
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ,
        ]);

        // 正しい引数順: hasDirectPermission(Role $role, string $permission)
        $this->assertTrue(
            $this->folder->hasDirectPermission($this->role, FolderPermissionType::READ->value)
        );
    }

    public function test_has_direct_permission_returns_false_when_absent(): void
    {
        $this->assertFalse(
            $this->folder->hasDirectPermission($this->role, FolderPermissionType::READ->value)
        );
    }

    public function test_get_direct_permissions_returns_collection(): void
    {
        RoleFolderPermission::create([
            'role_id' => $this->role->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::WRITE,
        ]);

        $perms = $this->folder->getDirectPermissions($this->role);
        $this->assertNotEmpty($perms);
    }

    // ----------------------------------------------------------------
    // descendantCount
    // ----------------------------------------------------------------

    public function test_descendant_count_returns_zero_for_leaf(): void
    {
        $this->assertEquals(0, $this->folder->descendantCount());
    }

    public function test_descendant_count_includes_children(): void
    {
        $child = Folder::factory()->create(['parent_id' => $this->folder->id]);
        // nestedset の rebuild が必要
        Folder::fixTree();
        $this->folder->refresh();
        $this->assertGreaterThan(0, $this->folder->descendantCount());
    }

    // ----------------------------------------------------------------
    // folder() — self-reference (return $this)
    // ----------------------------------------------------------------

    public function test_folder_self_reference_returns_self(): void
    {
        // Folder::folder() は return $this なので自分自身を返す
        $this->assertSame($this->folder->id, $this->folder->folder()->id);
    }

    // ----------------------------------------------------------------
    // requiredInspectorRoles / requiredApproverRoles
    // ----------------------------------------------------------------

    public function test_required_inspector_roles_returns_collection(): void
    {
        $this->assertNotNull($this->folder->requiredInspectorRoles());
    }

    public function test_required_approver_roles_returns_collection(): void
    {
        $this->assertNotNull($this->folder->requiredApproverRoles());
    }
}
