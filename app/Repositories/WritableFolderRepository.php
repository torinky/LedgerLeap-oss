<?php

namespace App\Repositories;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class WritableFolderRepository
{
    /**
     * ユーザーが書き込み可能なフォルダのIDを、指定されたフォルダの子孫も含めて取得する
     * フォルダが指定されていない場合は、ユーザーが書き込み可能なすべてのフォルダIDを子孫フォルダも含めて取得する
     *
     * @param User $user
     * @param Folder|null $folder 制限をかけたいフォルダ
     * @return array
     */
    public function getWritableFolderIds(User $user, ?Folder $folder = null): array
    {

        $cacheKey = "user_{$user->id}_writable_folders";
        if ($folder) {
            $cacheKey .= "_under_" . $folder->id;
        }

        return Cache::remember(
            $cacheKey,
            config('cache.writable_folders_ttl', 60),
            function () use ($user, $folder) {
                $userRoles = $user->getAllRoles();

                // フォルダーが指定されていない場合、ユーザーが書き込み可能な全てのフォルダーIDを子孫を含めて取得する。
                $allWritableFolderIds = $userRoles->flatMap(function ($role) {
                    return $role->writableFolders()->get()->flatMap(function ($folder) {
                        return $folder->descendantsAndSelf($folder->id);
                    })->pluck('id');
                })->unique();

                if (!is_null($folder)) {
                    // フォルダーが指定されている場合、指定されたフォルダーの子孫を含めて取得
                    $descendantIds = $folder->descendantsAndSelf($folder->id)->pluck('id')->toArray();
                    $allWritableFolderIds = $allWritableFolderIds->intersect($descendantIds);
                }
                return $allWritableFolderIds->toArray();
            }
        );
    }

    public function refreshWritableFolderCache(User $user): void
    {
        // userに関連するすべてのキャッシュキーをクリア
        $this->clearWritableFolderCache($user);

        // キャッシュを再生成
        $this->getWritableFolderIds($user);
    }

    public function clearWritableFolderCache(User $user): void
    {
        Cache::forget("user_{$user->id}_writable_folders"); // 基本のキャッシュキー
        // ルートフォルダと全ての子フォルダを取得
        $rootFolders = Folder::whereIsRoot()->get();
        $allFolders = $rootFolders->flatMap(function ($folder) {
            return $folder->descendantsAndSelf($folder->id);
        });
        // フォルダIDを使ってキャッシュキーを生成し、削除
        foreach ($allFolders as $folder) {
            Cache::forget("user_{$user->id}_writable_folders_under_{$folder->id}");
        }
    }

    /**
     * ユーザーが読み取り可能なフォルダのIDを、指定されたフォルダの子孫も含めて取得する
     * フォルダが指定されていない場合は、ユーザーが読み取り可能なすべてのフォルダIDを子孫フォルダも含めて取得する
     *
     * @param User $user
     * @param Folder|null $folder 制限をかけたいフォルダ
     * @return array
     */
    public function getReadableFolderIds(User $user, ?Folder $folder = null): array
    {
        $cacheKey = "user_{$user->id}_readable_folders";
        if ($folder) {
            $cacheKey .= "_under_" . $folder->id;
        }

        return Cache::remember(
            $cacheKey,
            config('cache.readable_folders_ttl', 60), // 読み取り用のキャッシュ時間
            function () use ($user, $folder) {
                $userRoles = $user->getAllRoles();

                // フォルダーが指定されていない場合、ユーザーが読み取り可能な全てのフォルダーIDを子孫を含めて取得する。
                $allReadableFolderIds = $userRoles->flatMap(function ($role) {
                    return $role->readableFolders()->get()->flatMap(function ($folder) {
                        return $folder->descendantsAndSelf($folder->id);
                    })->pluck('id');
                })->unique();

                if (!is_null($folder)) {
                    // フォルダーが指定されている場合、指定されたフォルダーの子孫を含めて取得
                    $descendantIds = $folder->descendantsAndSelf($folder->id)->pluck('id')->toArray();
                    $allReadableFolderIds = $allReadableFolderIds->intersect($descendantIds);
                }
//dd($user,$user->getRoleNames(),$userRoles,$allReadableFolderIds);
                return $allReadableFolderIds->toArray();
            }
        );
    }

    public function refreshReadableFolderCache(User $user): void
    {
        // userに関連するすべてのキャッシュキーをクリア
        $this->clearReadableFolderCache($user);

        // キャッシュを再生成
        $this->getReadableFolderIds($user);
    }

    public function clearReadableFolderCache(User $user): void
    {
        Cache::forget("user_{$user->id}_readable_folders"); // 基本のキャッシュキー
        // ルートフォルダと全ての子フォルダを取得
        $rootFolders = Folder::whereIsRoot()->get();
        $allFolders = $rootFolders->flatMap(function ($folder) {
            return $folder->descendantsAndSelf($folder->id);
        });
        // フォルダIDを使ってキャッシュキーを生成し、削除
        foreach ($allFolders as $folder) {
            Cache::forget("user_{$user->id}_readable_folders_under_{$folder->id}");
        }
    }
}
