<?php

namespace App\Observers;

use App\Models\RoleFolderPermission;
use App\Repositories\WritableFolderRepository;
use App\Services\TenantAccessService;
use App\Services\UserService;

class RoleFolderPermissionObserver
{
    protected WritableFolderRepository $writableFolderRepository;

    protected TenantAccessService $tenantAccessService;

    protected UserService $userService;

    public function __construct(WritableFolderRepository $writableFolderRepository, TenantAccessService $tenantAccessService, UserService $userService)
    {
        $this->writableFolderRepository = $writableFolderRepository;
        $this->tenantAccessService = $tenantAccessService;
        $this->userService = $userService;
    }

    public function created(RoleFolderPermission $roleFolderPermission): void
    {
        $this->tenantAccessService->clearAllCache();
    }

    public function updated(RoleFolderPermission $roleFolderPermission): void
    {
        $this->tenantAccessService->clearAllCache();
    }

    public function deleted(RoleFolderPermission $roleFolderPermission): void
    {
        // LogsActivityトレイトがdeletedイベントを処理する際にキャッシュクリアが二重に発生するため、ここでは呼び出さない
        // $this->tenantAccessService->clearAllCache();
    }
}
