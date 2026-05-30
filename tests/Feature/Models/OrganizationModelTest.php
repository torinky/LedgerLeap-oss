<?php

namespace Tests\Feature\Models;

use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Organization::class)]
class OrganizationModelTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private Organization $org;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->seed(RolesAndPermissionsSeeder::class);
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->org = Organization::create(['name' => 'Test Org '.uniqid()]);
        $this->role = Role::create(['name' => 'org-role-'.uniqid()]);
    }

    // ----------------------------------------------------------------
    // users リレーション
    // ----------------------------------------------------------------

    public function test_users_relation_returns_assigned_users(): void
    {
        $user = User::factory()->create();
        $user->organizations()->attach($this->org->id);

        $this->assertTrue($this->org->users->contains('id', $user->id));
    }

    // ----------------------------------------------------------------
    // getAllRoles / getDirectRoles / getInheritedRoles
    // ----------------------------------------------------------------

    public function test_get_direct_roles_returns_assigned_roles(): void
    {
        $this->org->roles()->attach($this->role->id);

        $directRoles = $this->org->getDirectRoles();
        $this->assertTrue($directRoles->contains('id', $this->role->id));
    }

    public function test_get_inherited_roles_returns_ancestor_roles(): void
    {
        $parent = Organization::create(['name' => 'Parent Org '.uniqid()]);
        $parent->roles()->attach($this->role->id);

        // $this->org を parent の子として設定
        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $inherited = $this->org->getInheritedRoles();
        $this->assertTrue($inherited->contains('id', $this->role->id));
    }

    public function test_get_all_roles_merges_direct_and_inherited(): void
    {
        $parentRole = Role::create(['name' => 'parent-role-'.uniqid()]);
        $parent = Organization::create(['name' => 'Parent Org '.uniqid()]);
        $parent->roles()->attach($parentRole->id);

        $this->org->roles()->attach($this->role->id);
        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $allRoles = $this->org->getAllRoles();
        $this->assertTrue($allRoles->contains('id', $this->role->id));
        $this->assertTrue($allRoles->contains('id', $parentRole->id));
    }

    public function test_get_all_roles_deduplicates(): void
    {
        // 同じロールを直接・継承両方で持つ場合、1件になる
        $parent = Organization::create(['name' => 'Parent Org '.uniqid()]);
        $parent->roles()->attach($this->role->id);
        $this->org->roles()->attach($this->role->id);
        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $allRoles = $this->org->getAllRoles();
        $this->assertEquals(1, $allRoles->where('id', $this->role->id)->count());
    }

    // ----------------------------------------------------------------
    // getAllUniqueRoles
    // ----------------------------------------------------------------

    public function test_get_all_unique_roles_returns_unique_collection(): void
    {
        $this->org->roles()->attach($this->role->id);

        $result = $this->org->getAllUniqueRoles();
        $this->assertTrue($result->contains('id', $this->role->id));
        // 重複なし
        $this->assertEquals($result->count(), $result->unique('id')->count());
    }

    // ----------------------------------------------------------------
    // hasRoleWithInheritance
    // ----------------------------------------------------------------

    public function test_has_role_with_inheritance_returns_true_for_direct_role(): void
    {
        $this->org->roles()->attach($this->role->id);

        $this->assertTrue($this->org->hasRoleWithInheritance($this->role->name));
    }

    public function test_has_role_with_inheritance_returns_false_when_no_role(): void
    {
        $this->assertFalse($this->org->hasRoleWithInheritance($this->role->name));
    }

    // ----------------------------------------------------------------
    // getFullNameAttribute
    // ----------------------------------------------------------------

    public function test_get_full_name_attribute_returns_name_for_root(): void
    {
        // ルート組織はそのまま名前が返る
        $this->assertStringContainsString('Test Org', $this->org->getFullNameAttribute());
    }

    // ----------------------------------------------------------------
    // getTreeLabelAttribute
    // ----------------------------------------------------------------

    public function test_get_tree_label_attribute_returns_string(): void
    {
        $this->assertIsString($this->org->getTreeLabelAttribute());
    }

    // ----------------------------------------------------------------
    // getAllPermissions / getDirectPermissions / getInheritedPermissions
    // ----------------------------------------------------------------

    public function test_get_direct_permissions_returns_assigned_permissions(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'test-perm-'.uniqid(), 'guard_name' => 'web']);
        $this->org->givePermissionTo($permission);

        $direct = $this->org->getDirectPermissions();
        $this->assertTrue($direct->contains('id', $permission->id));
    }

    public function test_get_inherited_permissions_returns_ancestor_permissions(): void
    {
        $parent = Organization::create(['name' => 'Parent Perm Org '.uniqid()]);
        $permission = Permission::firstOrCreate(['name' => 'inherited-perm-'.uniqid(), 'guard_name' => 'web']);
        $parent->givePermissionTo($permission);

        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $inherited = $this->org->getInheritedPermissions();
        $this->assertTrue($inherited->contains('id', $permission->id));
    }

    public function test_get_all_permissions_merges_direct_and_inherited(): void
    {
        $parent = Organization::create(['name' => 'Parent All Perm '.uniqid()]);
        $parentPerm = Permission::firstOrCreate(['name' => 'parent-perm-'.uniqid(), 'guard_name' => 'web']);
        $directPerm = Permission::firstOrCreate(['name' => 'direct-perm-'.uniqid(), 'guard_name' => 'web']);

        $parent->givePermissionTo($parentPerm);
        $this->org->givePermissionTo($directPerm);
        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $all = $this->org->getAllPermissions();
        $this->assertTrue($all->contains('id', $directPerm->id));
        $this->assertTrue($all->contains('id', $parentPerm->id));
    }

    public function test_get_all_permissions_deduplicates(): void
    {
        $parent = Organization::create(['name' => 'Parent Dup Perm '.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'dup-perm-'.uniqid(), 'guard_name' => 'web']);

        $parent->givePermissionTo($perm);
        $this->org->givePermissionTo($perm);
        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $all = $this->org->getAllPermissions();
        $this->assertEquals(1, $all->where('id', $perm->id)->count());
    }

    // ----------------------------------------------------------------
    // hasPermissionWithInheritance
    // ----------------------------------------------------------------

    public function test_has_permission_with_inheritance_true_for_direct(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'direct-check-'.uniqid(), 'guard_name' => 'web']);
        $this->org->givePermissionTo($perm);

        $this->assertTrue($this->org->hasPermissionWithInheritance($perm->name));
    }

    public function test_has_permission_with_inheritance_true_for_inherited(): void
    {
        $parent = Organization::create(['name' => 'Parent Inherit Check '.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'inherit-check-'.uniqid(), 'guard_name' => 'web']);
        $parent->givePermissionTo($perm);

        $this->org->appendToNode($parent)->save();
        $this->org->refresh();

        $this->assertTrue($this->org->hasPermissionWithInheritance($perm->name));
    }

    public function test_has_permission_with_inheritance_false_when_none(): void
    {
        // 存在するが付与していないパーミッション → false
        $perm = Permission::firstOrCreate(['name' => 'not-assigned-perm-'.uniqid(), 'guard_name' => 'web']);
        $this->assertFalse($this->org->hasPermissionWithInheritance($perm->name));
    }

    // ----------------------------------------------------------------
    // getAllUniquePermissions / getAllUniquePermissionsViaRoles
    // ----------------------------------------------------------------

    public function test_get_all_unique_permissions_returns_unique(): void
    {
        $perm = Permission::firstOrCreate(['name' => 'unique-perm-'.uniqid(), 'guard_name' => 'web']);
        $this->org->givePermissionTo($perm);

        $result = $this->org->getAllUniquePermissions();
        $this->assertTrue($result->contains('id', $perm->id));
        $this->assertEquals($result->count(), $result->unique('id')->count());
    }
}
