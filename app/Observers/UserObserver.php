<?php

namespace App\Observers;

use App\Models\User;
use App\Services\TenantAccessService;

class UserObserver
{
    public function __construct(protected TenantAccessService $tenantAccessService) {}

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // ユーザーのロール割り当て変更など、テナントへのアクセス権に影響する可能性のある
        // 更新が行われた際に、キャッシュをクリアする。
        $this->tenantAccessService->clearUserCache($user);
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        $this->tenantAccessService->clearUserCache($user);
    }
}
