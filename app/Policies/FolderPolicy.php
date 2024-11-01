<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
        //
    }

    public function restore(User $user, Folder $folder)
    {
        // ユーザーのロールに紐付けられたフォルダーにアクセスしようとしているフォルダーが含まれているかどうかをチェック
        $userRoles = $user->roles;
        $accessibleFolders = $userRoles->flatMap->folders;
        $hasRoleInFolder = $accessibleFolders->contains($folder);

        // ロールのパーミッションに従うかどうかをチェック
        $hasPermission = $user->hasPermissionTo('restore_folders');

        return $hasRoleInFolder && $hasPermission;
    }

    public function forceDelete(User $user, Folder $folder)
    {
        // ユーザーのロールに紐付けられたフォルダーにアクセスしようとしているフォルダーが含まれているかどうかをチェック
        $userRoles = $user->roles;
        $accessibleFolders = $userRoles->flatMap->folders;
        $hasRoleInFolder = $accessibleFolders->contains($folder);

        // ロールのパーミッションに従うかどうかをチェック
        $hasPermission = $user->hasPermissionTo('force_delete_folders');

        return $hasRoleInFolder && $hasPermission;
    }

    public function view(User $user, Folder $folder)
    {
        // ユーザーのロールに紐付けられたフォルダーにアクセスしようとしているフォルダーが含まれているかどうかをチェック
        $userRoles = $user->roles;
        $accessibleFolders = $userRoles->flatMap->folders;
        $hasRoleInFolder = $accessibleFolders->contains($folder);

        // ロールのパーミッションに従うかどうかをチェック
        $hasPermission = $user->hasPermissionTo('view_folders');

        return $hasRoleInFolder && $hasPermission;
    }

    public function create(User $user)
    {
        // ユーザーのロールに紐付けられたフォルダーにアクセスしようとしているフォルダーが含まれているかどうかをチェック
        $userRoles = $user->roles;
        $accessibleFolders = $userRoles->flatMap->folders;
        $hasRoleInFolder = $accessibleFolders->contains(auth()->user()->current_folder);

        // ロールのパーミッションに従うかどうかをチェック
        $hasPermission = $user->hasPermissionTo('create_folders');

        return $hasRoleInFolder && $hasPermission;
    }

    public function update(User $user, Folder $folder)
    {
        // ユーザーのロールに紐付けられたフォルダーにアクセスしようとしているフォルダーが含まれているかどうかをチェック
        $userRoles = $user->roles;
        $accessibleFolders = $userRoles->flatMap->folders;
        $hasRoleInFolder = $accessibleFolders->contains($folder);

        // ロールのパーミッションに従うかどうかをチェック
        $hasPermission = $user->hasPermissionTo('update_folders');

        return $hasRoleInFolder && $hasPermission;
    }

    public function delete(User $user, Folder $folder)
    {
        // ユーザーのロールに紐付けられたフォルダーにアクセスしようとしているフォルダーが含まれているかどうかをチェック
        $userRoles = $user->roles;
        $accessibleFolders = $userRoles->flatMap->folders;
        $hasRoleInFolder = $accessibleFolders->contains($folder);

        // ロールのパーミッションに従うかどうかをチェック
        $hasPermission = $user->hasPermissionTo('delete_folders');

        return $hasRoleInFolder && $hasPermission;
    }
}
