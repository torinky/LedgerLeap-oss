<?php

namespace App\Repositories;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WritableFolderRepository
{
    private UserService $userService;

    public function __construct()
    {
        $this->userService = new UserService($this);
    }

    /**
     * ユーザーが指定された権限でアクセス可能なフォルダのIDを、指定されたフォルダの子孫も含めて取得する
     * フォルダが指定されていない場合は、ユーザーがアクセス可能なすべてのフォルダIDを子孫フォルダも含めて取得する
     *
     * @param FolderPermissionType $permission 'read', 'write', 'manageable' など
     * @param Folder|null $folder 制限をかけたいフォルダ
     */
    public function getAccessibleFolderIds(User $user, FolderPermissionType $permission, ?Folder $folder = null): array
    {
        $cacheKey = $this->getCacheKey($user, $permission->value, $folder);

        return Cache::remember(
            $cacheKey,
            config("cache.{$permission->value}able_folders_ttl", 60),
            function () use ($user, $permission, $folder) {
                //                $userRoles = $user->getAllRoles();
                $userRoles = $this->userService->getAllUniqueRolesForUser($user);

                $allAccessibleFolderIds = $userRoles->flatMap(function ($role) use ($permission) {
                    return $role->accessibleFolders($permission)->get()->flatMap(function ($folder) {
                        return $folder->descendantsAndSelf($folder->id);
                    })->pluck('id');
                })->unique();

                if (!is_null($folder)) {
                    $descendantIds = $folder->descendantsAndSelf($folder->id)->pluck('id')->toArray();
                    $allAccessibleFolderIds = $allAccessibleFolderIds->intersect($descendantIds);
                }
                $allAccessibleFolderIds->add($folder);

                return $allAccessibleFolderIds->toArray();
            }
        );
    }

    /**
     * ユーザーが書き込み可能なフォルダのIDを取得する
     */
    public function getWritableFolderIds(User $user, ?Folder $folder = null): array
    {
        return $this->getAccessibleFolderIds($user, FolderPermissionType::WRITE, $folder);
    }

    /**
     * ユーザーが読み取り可能なフォルダのIDを取得する
     */
    public function getReadableFolderIds(User $user, ?Folder $folder = null): array
    {
        return $this->getAccessibleFolderIds($user, FolderPermissionType::READ, $folder);
    }

    public function getManageableFolderIds(User $user, ?Folder $folder = null): array
    {
        // スーパー管理者は常に全てのフォルダを管理可能
/*        if ($user->hasRole('Super Admin')) {
            return Folder::all()->pluck('id');
        }*/

        $cacheKey = "user_{$user->id}_manageable_folder_ids";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            $manageableFolderIds = collect();
            $userRoles = $user->roles->pluck('id');

            // ユーザーのロールが持つフォルダ権限から、管理可能なフォルダを特定
            $roleFolderPermissions = RoleFolderPermission::whereIn('role_id', $userRoles)
                ->where('permission', FolderPermissionType::ADMIN)
                ->get();

            foreach ($roleFolderPermissions as $roleFolderPermission) {
                $folder = Folder::find($roleFolderPermission->folder_id);
                if ($folder) {
                    $manageableFolderIds->push($folder->id);
                    // 子孫フォルダも管理可能とみなす
                    $manageableFolderIds = $manageableFolderIds->merge($folder->descendants->pluck('id'));
                }
            }

            return $manageableFolderIds->unique()->toArray();
        });
    }

    public function refreshFolderCache(User $user, string $permission): void
    {
        $this->clearFolderCache($user, $permission);
        $this->getAccessibleFolderIds($user, FolderPermissionType::from($permission));
    }

    public function clearFolderCache(User $user, string $permission): void
    {
        Cache::forget($this->getCacheKey($user, $permission));
        $rootFolders = Folder::whereIsRoot()->get();
        $allFolders = $rootFolders->flatMap(function ($folder) {
            return $folder->descendantsAndSelf($folder->id);
        });
        foreach ($allFolders as $folder) {
            Cache::forget($this->getCacheKey($user, $permission, $folder));
        }
    }

    /**
     * キャッシュキーを生成する
     */
    protected function getCacheKey(User $user, string $permission, ?Folder $folder = null): string
    {
        $cacheKey = "user_{$user->id}_{$permission}_folders";
        if ($folder) {
            $cacheKey .= '_under_' . $folder->id;
        }

        return $cacheKey;
    }

    /**
     * キャッシュをクリアする
     */
    public function clearAllCache(User $user): void
    {
        $permissions = array_column(FolderPermissionType::cases(), 'value');
        foreach ($permissions as $permission) {
            $this->clearFolderCache($user, $permission);
        }
    }

    public function refreshAllCache(User $user): void
    {
        $permissions = array_column(FolderPermissionType::cases(), 'value');
        foreach ($permissions as $permission) {
            $this->refreshFolderCache($user, $permission);
        }
    }
}
