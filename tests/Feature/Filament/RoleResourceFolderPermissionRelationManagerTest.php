<?php

namespace Tests\Feature\Filament;

use App\Enums\FolderPermissionType;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\RelationManagers\FolderPermissionRelationManager;
use App\Models\Folder;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FolderPermissionRelationManager::class)]
class RoleResourceFolderPermissionRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    private const PERMISSIONS = [
        'view_roles', 'create_roles', 'update_roles',
        'delete_roles', 'manage_roles',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'role-folder-permission-test.localhost']);
        tenancy()->initialize($this->tenant);

        foreach (self::PERMISSIONS as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);
        $adminRole->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    #[Test]
    public function editActionPrefillsAndSavesPermissions(): void
    {
        $role = Role::firstOrCreate(['name' => 'RolePermissionTest', 'guard_name' => 'web']);
        $folder = Folder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);

        $permission = RoleFolderPermission::query()->create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->adminUser->id,
        ]);

        Livewire::test(FolderPermissionRelationManager::class, [
            'ownerRecord' => $role,
            'pageClass' => EditRole::class,
        ])
            ->assertCanSeeTableRecords(RoleFolderPermission::query()
                ->get())
            ->mountTableAction('edit', $permission)
            ->assertTableActionDataSet([
                'permissions' => [FolderPermissionType::READ->value],
            ])
            ->setTableActionData([
                'permissions' => [
                    FolderPermissionType::READ->value,
                    FolderPermissionType::WRITE->value,
                ],
            ])
            ->callMountedTableAction();

        $this->assertDatabaseHas('role_folder_permissions', [
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::READ->value,
        ]);

        $this->assertDatabaseHas('role_folder_permissions', [
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => FolderPermissionType::WRITE->value,
        ]);

        $this->assertDatabaseCount('role_folder_permissions', 2);
    }
}
