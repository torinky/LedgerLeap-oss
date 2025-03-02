<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Permission のキャッシュをクリア
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 権限の定義 (description を追加)
        $permissions = [
            'view_users' => 'ユーザーの一覧を閲覧できる',
            'create_users' => 'ユーザーを作成できる',
            'update_users' => 'ユーザーを更新できる',
            'delete_users' => 'ユーザーを削除できる',

            'view_organizations' => '組織の一覧を閲覧できる',
            'create_organizations' => '組織を作成できる',
            'update_organizations' => '組織を更新できる',
            'delete_organizations' => '組織を削除できる',

            'view_roles' => '役割の一覧を閲覧できる',
            'create_roles' => '役割を作成できる',
            'update_roles' => '役割を更新できる',
            'delete_roles' => '役割を削除できる',
            'restore_roles' => '役割を復元できる',
            'force_delete_roles' => '役割を完全に削除できる',

            'view_rolefolderpermissions' => 'フォルダーの権限を閲覧できる',
            'create_rolefolderpermissions' => 'フォルダーの権限を作成できる',
            'update_rolefolderpermissions' => 'フォルダーの権限を更新できる',
            'delete_rolefolderpermissions' => 'フォルダーの権限を削除できる',

            'view_ledgers' => '台帳の一覧を閲覧できる',
            'create_ledgers' => '台帳を作成できる',
            'update_ledgers' => '台帳を更新できる',
            'delete_ledgers' => '台帳を削除できる',

            'view_ledgerdefines' => '台帳定義の一覧を閲覧できる',
            'create_ledgerdefines' => '台帳定義を作成できる',
            'update_ledgerdefines' => '台帳定義を更新できる',
            'delete_ledgerdefines' => '台帳定義を削除できる',
            'restore_ledgerdefines' => '台帳定義を復元できる',
            'force_delete_ledgerdefines' => '台帳定義を完全に削除できる',

            'view_folders' => 'フォルダーの一覧を閲覧できる',
            'create_folders' => 'フォルダーを作成できる',
            'update_folders' => 'フォルダーを更新できる',
            'delete_folders' => 'フォルダーを削除できる',
            'restore_folders' => 'フォルダーを復元できる',
            'force_delete_folders' => 'フォルダーを完全に削除できる',
            'notify' => '通知を受け取る',
            'view_permissions' => '権限を閲覧できる',
            'create_permissions' => '権限を作成できる',
            'update_permissions' => '権限を更新できる',
            'delete_permissions' => '権限を削除できる',
            'manage_permissions' => '権限を管理できる',
        ];
        // 権限を登録
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        // ロールを定義し、権限を付与
        $roles = [
            'Super Admin' => [
                'description' => 'システム全体の管理者',
                'permissions' => [
                    'view_users', 'create_users', 'update_users', 'delete_users',
                    'view_organizations', 'create_organizations', 'update_organizations', 'delete_organizations',
                    'view_roles', 'create_roles', 'update_roles', 'delete_roles', 'restore_roles', 'force_delete_roles',
                    'view_rolefolderpermissions', 'create_rolefolderpermissions', 'update_rolefolderpermissions', 'delete_rolefolderpermissions',
                    'view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers',
                    'view_ledgerdefines', 'create_ledgerdefines', 'update_ledgerdefines', 'delete_ledgerdefines', 'restore_ledgerdefines', 'force_delete_ledgerdefines',
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders', 'restore_folders', 'force_delete_folders',
                    'view_permissions', 'create_permissions', 'update_permissions', 'delete_permissions', 'manage_permissions',
                    'notify',
                ],
            ],
            'Organization Admin' => [
                'description' => '組織の管理者',
                'permissions' => [
                    'view_users', 'create_users', 'update_users', 'delete_users',
                    'view_organizations', 'create_organizations', 'update_organizations', 'delete_organizations',
                    'view_roles', 'create_roles', 'update_roles', 'delete_roles', 'restore_roles', 'force_delete_roles',
                    'view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers',
                    'view_ledgerdefines', 'create_ledgerdefines', 'update_ledgerdefines', 'delete_ledgerdefines', 'restore_ledgerdefines', 'force_delete_ledgerdefines',
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders', 'restore_folders', 'force_delete_folders',
                    'view_rolefolderpermissions', 'create_rolefolderpermissions', 'update_rolefolderpermissions', 'delete_rolefolderpermissions',
                ],
            ],
            'Project Manager' => [
                'description' => 'プロジェクトの管理者',
                'permissions' => [
                    'view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers',
                    'view_ledgerdefines', 'create_ledgerdefines', 'update_ledgerdefines', 'delete_ledgerdefines',
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders',
                ],
            ],
            'Editor' => [
                'description' => '台帳の編集者',
                'permissions' => [
                    'view_ledgers', 'create_ledgers', 'update_ledgers',
                    'view_ledgerdefines', 'create_ledgerdefines', 'update_ledgerdefines',
                    'view_folders',
                ],
            ],
            'Viewer' => [
                'description' => '台帳の閲覧者',
                'permissions' => [
                    'view_ledgers',
                    'view_ledgerdefines',
                    'view_folders',
                ],
            ],
            'Folder Manager' => [
                'description' => 'フォルダーの管理者',
                'permissions' => [
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders',
                    'view_ledgerdefines', 'create_ledgerdefines', 'update_ledgerdefines', 'delete_ledgerdefines',
                ],
            ],
            'Folder Viewer' => [
                'description' => 'フォルダーの閲覧者',
                'permissions' => [
                    'view_folders',
                    'view_ledgerdefines',
                ],
            ],
            'user' => [
                'description' => '通常の利用者',
                'permissions' => [
                    'view_ledgers',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            // ロールが存在しない場合は、作成する。
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ], [
                'description' => $roleData['description'],
            ]);
            // roleに、permissionを紐づける
            $role->syncPermissions($roleData['permissions']);
        }

        // super_admin@ll.com に Super Admin ロールを付与
        $user = User::where('email', 'super_admin@ll.com')->first();
        if ($user) {
            $superAdminRole = Role::where('name', 'Super Admin')->first();
            if ($superAdminRole && !$user->hasRole($superAdminRole)) {
                $user->assignRole($superAdminRole);
            }
        }
    }
}
