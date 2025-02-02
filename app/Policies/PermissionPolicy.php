<?php

namespace App\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;

class PermissionPolicy
{
    private UserService $userService;

    /**
     * PermissionPolicy のコンストラクタ。
     *
     * PermissionPolicy を UserService インスタンスで初期化します。
     *
     * @param UserService $userService ユーザー関連の操作を処理する UserService インスタンス。
     */
    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * ユーザーが権限を表示する権限があるかどうかを確認します。
     *
     * @param User $user 現在のユーザー。
     * @return bool ユーザーが権限を表示する権限がある場合は true、そうでない場合は false。
     *              このメソッドは、現在のユーザーが 'view_permissions' または 'manage_permissionss' 権限を持っているかどうかを確認します。
     */
    public function view(User $user)
    {
        return $this->userService->hasPermission($user, ['view_permissions', 'manage_permissions']);
    }

    /**
     * ユーザーが新しい権限を作成する権限があるかどうかを確認します。
     *
     * @param User $user 現在のユーザー。
     * @return bool ユーザーが新しい権限を作成する権限がある場合は true、そうでない場合は false。
     *              このメソッドは、現在のユーザーが 'create_permissions' または 'manage_permissions' 権限を持っているかどうかを確認します。
     */
    public function create(User $user)
    {
        Log::info('PermissionPolicy::create() called.'); // ログ出力を追加

//        dd($this->userService->getAllPermissionsForUser($user)->pluck('name'),$this->userService->hasPermission($user, ['create_permissions', 'manage_permissions']));
        return $this->userService->hasPermission($user, ['create_permissions', 'manage_permissions']);
    }

    /**
     * ユーザーが権限を更新する権限があるかどうかを確認します。
     *
     * @param User $user 現在のユーザー。
     * @return bool ユーザーが権限を更新する権限がある場合は true、そうでない場合は false。
     *              このメソッドは、現在のユーザーが 'update_permissions' または 'manage_permissions' 権限を持っているかどうかを確認します。
     */
    public function update(User $user)
    {
        return $this->userService->hasPermission($user, ['update_permissions', 'manage_permissions']);
    }

    /**
     * ユーザーが権限を削除する権限があるかどうかを確認します。
     *
     * @param User $user 現在のユーザー。
     * @return bool ユーザーが権限を削除する権限がある場合は true、そうでない場合は false。
     *              このメソッドは、現在のユーザーが 'delete_permissions' または 'manage_permissions' 権限を持っているかどうかを確認します。
     */
    public function delete(User $user)
    {
        return $this->userService->hasPermission($user, ['delete_permissions', 'manage_permissions']);
    }
}
