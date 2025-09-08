<?php

namespace App\Observers;

use App\Models\RoleFolderPermission;
use App\Repositories\WritableFolderRepository;
use App\Services\TenantAccessService;
use Illuminate\Support\Facades\Log;

class RoleFolderPermissionObserver
{
    protected WritableFolderRepository $writableFolderRepository;
    protected TenantAccessService $tenantAccessService;

    public function __construct(WritableFolderRepository $writableFolderRepository, TenantAccessService $tenantAccessService)
    {
        $this->writableFolderRepository = $writableFolderRepository;
        $this->tenantAccessService = $tenantAccessService;
    }

    public function created(RoleFolderPermission $roleFolderPermission): void
    {
        $this->clearCache($roleFolderPermission);
    }

    public function updated(RoleFolderPermission $roleFolderPermission): void
    {
        $this->clearCache($roleFolderPermission);
    }

    public function deleted(RoleFolderPermission $roleFolderPermission): void
    {
        // LogsActivityトレイトがdeletedイベントを処理する際にキャッシュクリアが二重に発生するため、ここでは呼び出さない
        // $this->clearCache($roleFolderPermission);
    }

    protected function clearCache(RoleFolderPermission $roleFolderPermission): void
    {
        Log::info('clearCache method entered.');
        $roleFolderPermission->load('role.users');
        Log::info('Role is loaded: ' . ($roleFolderPermission->role ? 'true' : 'false') . ' for RoleFolderPermission ID: ' . $roleFolderPermission->id . ' role_id: ' . $roleFolderPermission->role_id);

        if ($roleFolderPermission->role) {
            // Eager load users to avoid N+1 problem
            $users = $roleFolderPermission->role->users;
            Log::info('Users count: ' . $users->count());

            foreach ($users as $user) {
                $this->writableFolderRepository->clearAllCache($user);
                $this->tenantAccessService->clearUserCache($user);
            }
        }
    }
}
