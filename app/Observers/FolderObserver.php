<?php

namespace App\Observers;

use App\Models\Folder;
use App\Services\TenantAccessService;

class FolderObserver
{
    public function __construct(protected TenantAccessService $tenantAccessService)
    {
    }

    /**
     * Handle the Folder "saved" event.
     */
    public function saved(Folder $folder): void
    {
        // 親IDまたはテナントIDが変更された場合、全ユーザーのテナントアクセスキャッシュをクリア
        if ($folder->wasChanged('parent_id') || $folder->wasChanged('tenant_id')) {
            $this->tenantAccessService->clearAllCache();
        }
    }

    /**
     * Handle the Folder "deleted" event.
     */
    public function deleted(Folder $folder): void
    {
        $this->tenantAccessService->clearAllCache();
    }
}
