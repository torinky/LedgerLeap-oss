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
            'view_ledger_defines',
            'create_ledger_defines',
            'update_ledger_defines',
            'delete_ledger_defines',
            'restore_ledger_defines',
            'force_delete_ledger_defines',
            'create_roles',
            'update_roles',
            'delete_roles',
            'restore_roles',
            'force_delete_roles',
        ];

        foreach ($permissions as $permission) {
            if (!Permission::whereName($permission)->exists()) {
                Permission::create(['name' => $permission]);
            }
        }

        // Create roles and assign permissions
        $roles = [
            'Super Admin' => $permission,
            'Organization Admin' => [
                'view_ledgers',
                'edit_ledgers',
                'delete_ledgers',
                'manage_ledger_defines',
                'manage_folders',
                'manage_tags',
                'view_folders',
                'create_folders',
                'update_folders',
                'delete_folders',
                'restore_folders',
                'force_delete_folders',
                'view_ledger_defines',
                'create_ledger_defines',
                'update_ledger_defines',
                'delete_ledger_defines',
                'restore_ledger_defines',
                'force_delete_ledger_defines',
                'create_roles',
                'update_roles',
                'delete_roles',
                'restore_roles',
                'force_delete_roles',
            ],
            'Project Manager' => [
                'view_ledgers',
                'edit_ledgers',
                'manage_ledger_defines',
                'manage_folders',
                'manage_tags',
                'view_folders',
                'create_folders',
                'update_folders',
                'delete_folders',
                'restore_folders',
                'force_delete_folders',
                'view_ledger_defines',
                'create_ledger_defines',
                'update_ledger_defines',
                'delete_ledger_defines',
                'restore_ledger_defines',
                'force_delete_ledger_defines',
            ],
            'Editor' => [
                'view_ledgers',
                'edit_ledgers',
                'view_folders',
                'view_ledger_defines',
                'create_ledger_defines',
                'update_ledger_defines',
                'delete_ledger_defines',
                'restore_ledger_defines',
                'force_delete_ledger_defines',
            ],
            'Viewer' => [
                'view_ledgers',
                'view_folders',
                'view_ledger_defines',
            ],
            'Folder Manager' => [
                'view_folders',
                'create_folders',
                'update_folders',
                'delete_folders',
                'restore_folders',
                'force_delete_folders',
                'view_ledger_defines',
                'create_ledger_defines',
                'update_ledger_defines',
                'delete_ledger_defines',
                'restore_ledger_defines',
                'force_delete_ledger_defines',
            ],
            'Folder Viewer' => [
                'view_folders',
                'view_ledger_defines',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }

        // Assign Super Admin role to user aaa
        $user = User::whereEmail('super_admin@ll.com')->first();
        if ($user) {
            $superAdminRole = Role::where('name', 'Super Admin')->first();
            $user->assignRole($superAdminRole);
        }
    }
}
