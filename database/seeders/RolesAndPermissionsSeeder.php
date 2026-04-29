<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = $this->seedPermissions();
        $defaultEmailPermissions = $this->defaultEmailPermissions();

        $this->seedRoles($permissions, $defaultEmailPermissions);
        $this->assignSuperAdminRole();
    }

    private function seedPermissions(): array
    {
        $permissions = [
            'view_users' => 'ユーザーの一覧を閲覧できる',
            'create_users' => 'ユーザーを作成できる',
            'update_users' => 'ユーザーを更新できる',
            'delete_users' => 'ユーザーを削除できる',
            'manage_users' => 'ユーザーを管理できる',

            'view_organizations' => '組織の一覧を閲覧できる',
            'create_organizations' => '組織を作成できる',
            'update_organizations' => '組織を更新できる',
            'delete_organizations' => '組織を削除できる',
            'manage_organizations' => '組織を管理できる',

            'view_roles' => '役割の一覧を閲覧できる',
            'create_roles' => '役割を作成できる',
            'update_roles' => '役割を更新できる',
            'delete_roles' => '役割を削除できる',
            'restore_roles' => '役割を復元できる',
            'force_delete_roles' => '役割を完全に削除できる',

            'view_permissions' => '権限を閲覧できる',
            'create_permissions' => '権限を作成できる',
            'update_permissions' => '権限を更新できる',
            'delete_permissions' => '権限を削除できる',
            'manage_permissions' => '権限を管理できる',

            'view_folder_permissions' => 'フォルダーの権限設定を閲覧できる',
            'create_folder_permissions' => 'フォルダーの権限設定を作成できる',
            'update_folder_permissions' => 'フォルダーの権限設定を更新できる',
            'delete_folder_permissions' => 'フォルダーの権限設定を削除できる',

            'view_ledgers' => '台帳の一覧を閲覧できる',
            'create_ledgers' => '台帳を作成できる',
            'update_ledgers' => '台帳を更新できる',
            'delete_ledgers' => '台帳を削除できる',

            'view_ledger_defines' => '台帳定義の一覧を閲覧できる',
            'create_ledger_defines' => '台帳定義を作成できる',
            'update_ledger_defines' => '台帳定義を更新できる',
            'delete_ledger_defines' => '台帳定義を削除できる',
            'restore_ledger_defines' => '台帳定義を復元できる',
            'force_delete_ledger_defines' => '台帳定義を完全に削除できる',

            'view_folders' => 'フォルダーの一覧を閲覧できる',
            'create_folders' => 'フォルダーを作成できる',
            'update_folders' => 'フォルダーを更新できる',
            'delete_folders' => 'フォルダーを削除できる',
            'restore_folders' => 'フォルダーを復元できる',
            'force_delete_folders' => 'フォルダーを完全に削除できる',

            'manage_auto_links' => '自動リンクを管理できる',
            'notify' => '通知を受け取る（システム内）',
            'view_activity_logs' => 'アクティビティログを閲覧できる',

            'receive_workflow_summary_email' => 'ワークフローの集約通知メールを受け取る',
            'receive_workflow_action_email' => 'ワークフローの個別アクション通知メールを受け取る',

            'manage_attachments' => '添付ファイルの高度な管理（VLM再処理等）ができる',

            'create_admin_announcements' => '管理者お知らせを作成できる',
            'update_admin_announcements' => '管理者お知らせを編集できる',
            'delete_admin_announcements' => '管理者お知らせを削除できる',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ], [
                'description' => $description,
            ]);
        }

        return $permissions;
    }

    private function defaultEmailPermissions(): array
    {
        return [
            'receive_workflow_summary_email',
            'receive_workflow_action_email',
        ];
    }

    private function seedRoles(array $permissions, array $defaultEmailPermissions): void
    {
        $roles = [
            Role::SUPER_ADMIN => [
                'description' => 'システム全体の管理者',
                'permissions' => array_keys($permissions),
            ],
            'Organization Admin' => [
                'description' => '組織の管理者',
                'permissions' => array_merge([
                    'view_users',
                    'create_users',
                    'update_users',
                    'delete_users',
                    'view_organizations',
                    'create_organizations',
                    'update_organizations',
                    'delete_organizations',
                    'view_roles',
                    'create_roles',
                    'update_roles',
                    'delete_roles',
                    'restore_roles',
                    'force_delete_roles',
                    'view_ledgers',
                    'create_ledgers',
                    'update_ledgers',
                    'delete_ledgers',
                    'view_ledger_defines',
                    'create_ledger_defines',
                    'update_ledger_defines',
                    'delete_ledger_defines',
                    'restore_ledger_defines',
                    'force_delete_ledger_defines',
                    'view_folders',
                    'create_folders',
                    'update_folders',
                    'delete_folders',
                    'restore_folders',
                    'force_delete_folders',
                    'view_folder_permissions',
                    'create_folder_permissions',
                    'update_folder_permissions',
                    'delete_folder_permissions',
                    'view_activity_logs',
                    'manage_auto_links',
                    'manage_attachments',
                    'create_admin_announcements',
                    'update_admin_announcements',
                    'delete_admin_announcements',
                    'notify',
                ], $defaultEmailPermissions),
            ],
            'Project Manager' => [
                'description' => 'プロジェクトの管理者',
                'permissions' => array_merge([
                    'view_ledgers',
                    'create_ledgers',
                    'update_ledgers',
                    'delete_ledgers',
                    'view_ledger_defines',
                    'create_ledger_defines',
                    'update_ledger_defines',
                    'delete_ledger_defines',
                    'view_folders',
                    'create_folders',
                    'update_folders',
                    'delete_folders',
                    'view_activity_logs',
                    'create_admin_announcements',
                    'update_admin_announcements',
                    'notify',
                ], $defaultEmailPermissions),
            ],
            'Editor' => [
                'description' => '台帳の編集者',
                'permissions' => array_merge([
                    'view_ledgers',
                    'create_ledgers',
                    'update_ledgers',
                    'view_ledger_defines',
                    'create_ledger_defines',
                    'update_ledger_defines',
                    'view_folders',
                    'create_admin_announcements',
                    'update_admin_announcements',
                    'notify',
                ], $defaultEmailPermissions),
            ],
            'Viewer' => [
                'description' => '台帳の閲覧者',
                'permissions' => [
                    'view_ledgers',
                    'view_ledger_defines',
                    'view_folders',
                ],
            ],
            'Folder Manager' => [
                'description' => 'フォルダーの管理者',
                'permissions' => [
                    'view_folders',
                    'create_folders',
                    'update_folders',
                    'delete_folders',
                    'view_ledger_defines',
                    'create_ledger_defines',
                    'update_ledger_defines',
                    'delete_ledger_defines',
                ],
            ],
            'Folder Viewer' => [
                'description' => 'フォルダーの閲覧者',
                'permissions' => [
                    'view_folders',
                    'view_ledger_defines',
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
            $role = Role::updateOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ], [
                'description' => $roleData['description'],
            ]);

            $role->syncPermissions($roleData['permissions']);
        }
    }

    private function assignSuperAdminRole(): void
    {
        $user = User::query()->firstWhere('email', 'super_admin@ll.com');

        if (! $user) {
            return;
        }

        $superAdminRole = Role::query()->firstWhere('name', Role::SUPER_ADMIN);

        if ($superAdminRole && ! $user->hasRole($superAdminRole)) {
            $user->assignRole($superAdminRole);
        }
    }
}
