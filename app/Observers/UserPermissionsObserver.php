<?php

namespace App\Observers;

use App\Services\TenantAccessService;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Model;

class UserPermissionsObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    // public $afterCommit = true; // テストでトランザクションがコミットされないため、無効化

    protected UserService $userService;

    protected TenantAccessService $tenantAccessService;

    public function __construct(UserService $userService, TenantAccessService $tenantAccessService)
    {
        $this->userService = $userService;
        $this->tenantAccessService = $tenantAccessService;
    }

    /**
     * Handle the "updated" event.
     *
     * @return void
     */
    public function updated(Model $model)
    {
        \Illuminate\Support\Facades\Log::info('UserPermissionsObserver: updated event fired for '.get_class($model).' ID: '.$model->id);
        if ($model instanceof \App\Models\User) {
            $this->userService->clearUserPermissionsCache($model);
            $this->tenantAccessService->clearUserCache($model);
        } elseif ($model instanceof \App\Models\Role || $model instanceof \App\Models\Organization) {
            // RoleやOrganizationの変更は広範囲に影響するため、全キャッシュをクリアする
            \Illuminate\Support\Facades\Log::info('UserPermissionsObserver: Flushing all user permissions cache due to change in '.get_class($model));
            $this->userService->flushAllUserPermissionsCache();
            $this->tenantAccessService->clearAllCache();
        }
    }

    /**
     * Handle the "deleted" event.
     *
     * @return void
     */
    public function deleted(Model $model)
    {
        if ($model instanceof \App\Models\User) {
            $this->userService->clearUserPermissionsCache($model);
            $this->tenantAccessService->clearUserCache($model);
        } elseif ($model instanceof \App\Models\Role || $model instanceof \App\Models\Organization) {
            $this->userService->flushAllUserPermissionsCache();
            $this->tenantAccessService->clearAllCache();
        }
    }
}
