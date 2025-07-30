<?php

namespace App\Observers;

use App\Models\Folder;
use Illuminate\Support\Facades\Cache;

class FolderObserver
{
    /**
     * Handle the Folder "saved" event.
     */
    public function saved(Folder $folder): void
    {
        // 親IDが変更された場合のみキャッシュをクリア
        if ($folder->wasChanged('parent_id')) {
            Cache::tags('auto_links')->flush();
        }
    }

    /**
     * Handle the Folder "deleted" event.
     */
    public function deleted(Folder $folder): void
    {
        Cache::tags('auto_links')->flush();
    }
}
