<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view_ledgers',
            'edit_ledgers',
            'delete_ledgers',
            'manage_ledger_defines',
            'manage_folders',
            'manage_tags',
            'manage_users',
            'manage_roles',
            'manage_permissions',
            'view_folders',
            'create_folders',
            'update_folders',
            'delete_folders',
            'restore_folders',
            'force_delete_folders',
            'manage_folders',
            'view_folder_roles',
            'assign_folder_roles',
            'remove_folder_roles',];

        foreach ($permissions as $permission) {
            if (!Permission::whereName($permission)->exists()) {
                Permission::create(['name' => $permission]);
            }
        }

        // Create roles and assign permissions
        $roles = [
            'Super Admin' => $permissions,
            'Organization Admin' => $permissions,
            'Project Manager' => ['view_ledgers', 'edit_ledgers', 'manage_ledger_defines', 'manage_folders', 'manage_tags'],
            'Editor' => ['view_ledgers', 'edit_ledgers', 'view_folders'],
            'Viewer' => ['view_ledgers', 'view_folders',],
            'Folder Manager' => ['view_folders', 'create_folders', 'update_folders', 'delete_folders', 'restore_folders', 'force_delete_folders', 'manage_folders', 'view_folder_roles', 'assign_folder_roles', 'remove_folder_roles'],
            'Folder Viewer' => ['view_folders'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }

        // Assign Super Admin role to user aaa
        $user = User::where('name', 'aaa')->first();
        if ($user) {
            $superAdminRole = Role::where('name', 'Super Admin')->first();
            $user->assignRole($superAdminRole);
        }
    }
}
