<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class WritableFolderRepository
{
    public function refreshWritableFolderCache(User $user): void
    {
        Cache::forget($this->getWritableFolderCacheKey($user));
        $this->getWritableFolderIds($user); // キャッシュを再生成
    }

    protected function getWritableFolderCacheKey(User $user): string
    {
        return "user_writable_folder_ids_{$user->id}";
    }

    public function getWritableFolderIds(User $user)
    {
        $cacheKey = $this->getWritableFolderCacheKey($user);

        /*        return Cache::remember(
                    $cacheKey,
                    config('cache.writable_folders_ttl', 60),
                    function () use ($user) {*/
        $userRoles = $user->getAllRoles();
        // ユーザーのロールに基づき、書き込み可能なフォルダーのIDを取得
        $baseWritableFolders = $userRoles->flatMap(fn($role) => $role->writableFolders());
        $writableFolderIds = $baseWritableFolders->flatMap(fn($folder) => $folder->descendantsAndSelf($folder->id)->pluck('id'))->toArray();

        return $writableFolderIds;
        /*            }
                );*/
    }

    public function refreshReadableFolderCache(User $user): void
    {
        Cache::forget($this->getReadableFolderCacheKey($user));
        $this->getReadableFolderIds($user);
    }

    protected function getReadableFolderCacheKey(User $user): string
    {
        return "user_readable_folder_ids_{$user->id}";
    }

    public function getReadableFolderIds(User $user)
    {
        // ここに読み取り可能フォルダのIDを取得するロジックを実装します
        // 例：書き込み可能フォルダと同様に、ロールに基づいて読み取り可能なフォルダを取得する
        $cacheKey = $this->getReadableFolderCacheKey($user);

        return Cache::remember(
            $cacheKey,
            config('cache.readable_folders_ttl', 60), // 必要に応じて設定
            function () use ($user) {
                $userRoles = $user->getAllRoles();
                $baseReadableFolders = $userRoles->flatMap(fn($role) => $role->readableFolders());
                $readableFolderIds = $baseReadableFolders->flatMap(fn($folder) => $folder->descendantsAndSelf($folder->id)->pluck('id'))->toArray();

                return $readableFolderIds;
            }
        );
    }

    public function clearWritableFolderCache(User $user): void
    {
        Cache::forget($this->getWritableFolderCacheKey($user));
    }

    public function clearReadableFolderCache(User $user): void
    {
        Cache::forget($this->getReadableFolderCacheKey($user));
    }
}
