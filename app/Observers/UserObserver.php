<?php

namespace App\Observers;

use App\Models\User;
use App\Services\TenantAccessService;

class UserObserver
{
    /**
     * @param TenantAccessService $tenantAccessService
     */
    public function __construct(protected TenantAccessService $tenantAccessService)
    {
    }

    /**
     * Handle the User "updated" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function updated(User $user): void
    {
        // ユーザーのロール割り当て変更など、テナントへのアクセス権に影響する可能性のある
        // 更新が行われた際に、キャッシュをクリアする。
        $this->tenantAccessService->clearCache($user);
    }

    /**
     * Handle the User "deleted" event.
     *
     * @param  \App\Models\User  $user
     * @return void
     */
    public function deleted(User $user): void
    {
        $this->tenantAccessService->clearCache($user);
    }
}