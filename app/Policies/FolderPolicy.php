<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    use HandlesAuthorization;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        // ユーザーがフォルダの閲覧権限を持っているかどうかをチェック
        return $this->userService->getAllUniquePermissionsForUser($user)->contains('view_folders');
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Folder $folder
     * @return Response|bool
     */
    public function view(User $user, Folder $folder)
    {
        // ユーザーがフォルダの閲覧権限を持っているか、およびフォルダが閲覧可能かどうかをチェック
        return $this->userService->getAllUniquePermissionsForUser($user)->contains('view_folders')
            && $this->userService->isReadableFolderForUser($user, $folder);
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user)
    {
        // ユーザーがフォルダの作成権限を持っているかどうかをチェック
        return $this->userService->getAllUniquePermissionsForUser($user)->contains('create_folders');
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Folder $folder
     * @return Response|bool
     */
    public function update(User $user, Folder $folder)
    {
        // ユーザーがフォルダの更新権限を持っているか、およびフォルダが管理可能かどうかをチェック
        return $this->userService->hasPermission($user, 'manage_folders')
            && $this->userService->isManageableFolderForUser($user, $folder);
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Folder $folder
     * @return Response|bool
     */
    public function delete(User $user, Folder $folder)
    {
        // ユーザーがフォルダの削除権限を持っているか、およびフォルダが管理可能かどうかをチェック
        return $this->userService->getAllUniquePermissionsForUser($user)->contains('delete_folders')
            && $this->userService->isManageableFolderForUser($user, $folder);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param Folder $folder
     * @return Response|bool
     */
    public function restore(User $user, Folder $folder)
    {
        // ユーザーがフォルダの復元権限を持っているか、およびフォルダが管理可能かどうかをチェック
        return $this->userService->getAllUniquePermissionsForUser($user)->contains('restore_folders')
            && $this->userService->isManageableFolderForUser($user, $folder);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param Folder $folder
     * @return Response|bool
     */
    public function forceDelete(User $user, Folder $folder)
    {
        // ユーザーがフォルダの強制削除権限を持っているか、およびフォルダが管理可能かどうかをチェック
        return $this->userService->getAllUniquePermissionsForUser($user)->contains('force_delete_folders')
            && $this->userService->isManageableFolderForUser($user, $folder);
    }
}
