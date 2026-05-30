<?php

namespace App\Filament\Traits;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

trait HasPermissionMetadata
{
    /**
     * 権限名からグループキーを判定するヘルパーメソッド
     */
    protected static function getPermissionGroup(string $permissionName): ?string
    {
        if (Str::contains($permissionName, ['users'])) {
            return 'user';
        }
        if (Str::contains($permissionName, ['organizations'])) {
            return 'organization';
        }
        if (Str::contains($permissionName, ['roles'])) {
            return 'role';
        }
        if (Str::contains($permissionName, ['permissions'])) {
            return 'permission';
        } // 'manage_permissions' など
        if (Str::contains($permissionName, ['folder_permissions'])) {
            return 'folder_permission';
        } // フォルダー権限設定
        if (Str::contains($permissionName, ['ledgers']) && ! Str::contains($permissionName, ['define'])) {
            return 'ledger';
        } // 台帳操作
        if (Str::contains($permissionName, ['ledger_defines'])) {
            return 'ledger_define';
        } // 台帳定義
        if (Str::contains($permissionName, ['folders']) && ! Str::contains($permissionName, ['rolefolder'])) {
            return 'folder';
        } // フォルダ管理
        if (Str::contains($permissionName, ['workflow', 'email'])) {
            return 'workflow_notification';
        } // ワークフロー通知
        if (Str::contains($permissionName, ['notify'])) {
            return 'notification';
        } // システム内通知
        if (Str::contains($permissionName, ['activity_logs'])) {
            return 'activity_log';
        } // アクティビティログ

        return null; // グループが見つからない場合
    }

    /**
     * 整形された権限ラベルを取得するヘルパーメソッド
     */
    protected static function getFormattedPermissionLabel(Permission $permission): string
    {
        $group = self::getPermissionGroup($permission->name);
        $groupLabel = $group ? (__('permission.group.'.$group).' - ') : '';

        return $groupLabel.__('permission.name.'.$permission->name);
    }

    /**
     * グループ化・ソートされた権限オプションリストを取得する
     *
     * @return array<int, string>
     */
    protected static function getPermissionOptions(): array
    {
        $permissions = Permission::orderBy('name')->get();
        $groupedPermissions = $permissions->groupBy(function ($permission) {
            return self::getPermissionGroup($permission->name) ?? 'other';
        })->sortBy(function ($group, $key) {
            $order = [
                'user' => 1, 'organization' => 2, 'role' => 3, 'permission' => 4,
                'folder' => 5, 'folder_permission' => 6,
                'ledger_define' => 7, 'ledger' => 8,
                'workflow_notification' => 9, 'notification' => 10,
                'activity_log' => 11,
                'other' => 99,
            ];

            return $order[$key] ?? 99;
        });

        $options = [];
        foreach ($groupedPermissions as $groupKey => $permissionsInGroup) {
            foreach ($permissionsInGroup as $permission) {
                $options[$permission->id] = self::getFormattedPermissionLabel($permission);
            }
        }

        return $options;
    }
}
