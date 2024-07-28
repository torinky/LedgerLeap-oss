<?php

namespace Database\Seeders;

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
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        $roles = [
            'Super Admin' => $permissions,
            'Organization Admin' => $permissions,
            'Project Manager' => ['view_ledgers', 'edit_ledgers', 'manage_ledger_defines', 'manage_folders', 'manage_tags'],
            'Editor' => ['view_ledgers', 'edit_ledgers'],
            'Viewer' => ['view_ledgers'],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->givePermissionTo($rolePermissions);
        }
    }
}
