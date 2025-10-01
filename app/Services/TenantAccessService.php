<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TenantAccessService
{
    /**
     * ユーザーがアクセス可能なテナントのリストを取得します。
     * ユーザーのロールに紐づくフォルダ権限をすべて確認し、
     * 関連するテナントを重複なく返します。
     * 結果は24時間キャッシュされます。
     */
    public function getAccessibleTenants(User $user): Collection
    {
        $cacheKey = $this->getCacheKey($user);
        $cacheDuration = now()->addHours(24);

        // キャッシュタグを利用して、関連キャッシュをまとめて削除できるようにする
        return Cache::tags(['tenant_access'])->remember($cacheKey, $cacheDuration, function () use ($user) {
            // ユーザーが持つロールと、各ロールが権限を持つフォルダ（と、そのフォルダが属するテナント）をEager Loadする
            $roles = $user->roles()->with('folderPermissions.tenant')->get();

            // ロール -> フォルダ権限 -> テナント へと辿り、テナントのコレクションを生成する
            return $roles->pluck('folderPermissions')
                ->flatten() // 全ロールのフォルダ権限を一つのコレクションにまとめる
                ->pluck('tenant') // 各フォルダからテナントモデルを取得
                ->filter()      // null（テナントがない等のエッジケース）を除外
                ->unique('id')  // テナントIDで重複をなくす
                ->values();     // キーをリセットして返す
        });
    }

    /**
     * 特定のユーザーのテナントリストキャッシュをクリアします。
     */
    public function clearUserCache(User $user): void
    {
        Cache::forget($this->getCacheKey($user));
    }

    /**
     * 全てのテナントアクセス関連キャッシュをクリアします。
     */
    public function clearAllCache(): void
    {
        Cache::tags(['tenant_access'])->flush();
    }

    /**
     * キャッシュキーを生成します。
     */
    protected function getCacheKey(User $user): string
    {
        return "user.{$user->id}.accessible_tenants";
    }
}
