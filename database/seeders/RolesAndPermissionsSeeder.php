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

        // 権限の定義
        $permissions = [
            'view_users' => 'ユーザーの一覧を閲覧できる',
            'create_users' => 'ユーザーを作成できる',
            'update_users' => 'ユーザーを更新できる',
            'delete_users' => 'ユーザーを削除できる',
            'manage_users' => 'ユーザーを管理できる', // ユーザー管理グループ

            'view_organizations' => '組織の一覧を閲覧できる',
            'create_organizations' => '組織を作成できる',
            'update_organizations' => '組織を更新できる',
            'delete_organizations' => '組織を削除できる',
            'manage_organization' => '組織を管理できる', // 組織管理グループ

            'view_roles' => '役割の一覧を閲覧できる',
            'create_roles' => '役割を作成できる',
            'update_roles' => '役割を更新できる',
            'delete_roles' => '役割を削除できる',
            'restore_roles' => '役割を復元できる',
            'force_delete_roles' => '役割を完全に削除できる', // ロール管理グループ

            'view_permissions' => '権限を閲覧できる',
            'create_permissions' => '権限を作成できる',
            'update_permissions' => '権限を更新できる',
            'delete_permissions' => '権限を削除できる',
            'manage_permissions' => '権限を管理できる', // 権限管理グループ

            'view_folder_permissions' => 'フォルダーの権限設定を閲覧できる',
            'create_folder_permissions' => 'フォルダーの権限設定を作成できる',
            'update_folder_permissions' => 'フォルダーの権限設定を更新できる',
            'delete_folder_permissions' => 'フォルダーの権限設定を削除できる', // フォルダ権限設定グループ?

            'view_ledgers' => '台帳の一覧を閲覧できる',
            'create_ledgers' => '台帳を作成できる',
            'update_ledgers' => '台帳を更新できる',
            'delete_ledgers' => '台帳を削除できる', // 台帳操作グループ

            'view_ledger_defines' => '台帳定義の一覧を閲覧できる',
            'create_ledger_defines' => '台帳定義を作成できる',
            'update_ledger_defines' => '台帳定義を更新できる',
            'delete_ledger_defines' => '台帳定義を削除できる',
            'restore_ledger_defines' => '台帳定義を復元できる',
            'force_delete_ledger_defines' => '台帳定義を完全に削除できる', // 台帳定義管理グループ

            'view_folders' => 'フォルダーの一覧を閲覧できる',
            'create_folders' => 'フォルダーを作成できる',
            'update_folders' => 'フォルダーを更新できる',
            'delete_folders' => 'フォルダーを削除できる',
            'restore_folders' => 'フォルダーを復元できる',
            'force_delete_folders' => 'フォルダーを完全に削除できる', // フォルダ管理グループ

            'notify' => '通知を受け取る（システム内）', // 通知グループ
            'view_activity_logs' => 'アクティビティログを閲覧できる', // その他グループ

            'receive_workflow_summary_email' => 'ワークフローの集約通知メールを受け取る', // ワークフロー通知グループ
            'receive_workflow_action_email' => 'ワークフローの個別アクション通知メールを受け取る', // ワークフロー通知グループ

        ];

        // 権限を登録
        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate([ // updateOrCreate を使用して description も更新
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        // デフォルトでメール通知を受け取る権限
        $defaultEmailPermissions = [
            'receive_workflow_summary_email',
            'receive_workflow_action_email',
        ];

        // ロールを定義し、権限を付与
        $roles = [
            'Super Admin' => [
                'description' => 'システム全体の管理者',
                'permissions' => array_keys($permissions), // 全権限を持つ
            ],
            'Organization Admin' => [
                'description' => '組織の管理者',
                'permissions' => array_merge([ // 既存権限とメール通知権限をマージ
                    'view_users', 'create_users', 'update_users', 'delete_users',
                    'view_organizations', 'create_organizations', 'update_organizations', 'delete_organizations',
                    'view_roles', 'create_roles', 'update_roles', 'delete_roles', 'restore_roles', 'force_delete_roles',
                    'view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers',
                    'view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines', 'delete_ledger_defines', 'restore_ledger_defines', 'force_delete_ledger_defines',
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders', 'restore_folders', 'force_delete_folders',
                    'view_folder_permissions', 'create_folder_permissions', 'update_folder_permissions', 'delete_folder_permissions',
                    'view_activity_logs',
                    'notify', // システム内通知も受け取る想定
                ], $defaultEmailPermissions),
            ],
            'Project Manager' => [
                'description' => 'プロジェクトの管理者',
                'permissions' => array_merge([ // 既存権限とメール通知権限をマージ
                    'view_ledgers', 'create_ledgers', 'update_ledgers', 'delete_ledgers',
                    'view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines', 'delete_ledger_defines',
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders',
                    'view_activity_logs',
                    'notify', // システム内通知も受け取る想定
                ], $defaultEmailPermissions),
            ],
            'Editor' => [
                'description' => '台帳の編集者',
                'permissions' => array_merge([ // 既存権限とメール通知権限をマージ
                    'view_ledgers', 'create_ledgers', 'update_ledgers',
                    'view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines',
                    'view_folders',
                    'notify', // システム内通知も受け取る想定
                ], $defaultEmailPermissions),
            ],
            'Viewer' => [
                'description' => '台帳の閲覧者',
                'permissions' => [
                    'view_ledgers',
                    'view_ledger_defines',
                    'view_folders',
                    // メール通知はデフォルトでは付与しない
                ],
            ],
            'Folder Manager' => [
                'description' => 'フォルダーの管理者',
                'permissions' => [
                    'view_folders', 'create_folders', 'update_folders', 'delete_folders',
                    'view_ledger_defines', 'create_ledger_defines', 'update_ledger_defines', 'delete_ledger_defines',
                    // メール通知はデフォルトでは付与しない (必要なら追加)
                ],
            ],
            'Folder Viewer' => [
                'description' => 'フォルダーの閲覧者',
                'permissions' => [
                    'view_folders',
                    'view_ledger_defines',
                    // メール通知はデフォルトでは付与しない
                ],
            ],
            'user' => [ // 'user' ロールは権限が少ないため、メール通知はデフォルトOFFのまま
                'description' => '通常の利用者',
                'permissions' => [
                    'view_ledgers',
                ],
            ],
        ];

        foreach ($roles as $roleName => $roleData) {
            // ロールが存在しない場合は、作成する。 description も更新する
            $role = Role::updateOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ], [
                'description' => $roleData['description'],
            ]);
            // roleに、permissionを紐づける
            // syncPermissions を使うと既存の割り当てが解除されるため、必要な権限を全てリストする必要がある
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
