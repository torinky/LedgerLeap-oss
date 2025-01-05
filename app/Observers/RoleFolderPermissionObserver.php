<?php

namespace App\Observers;

use App\Models\RoleFolderPermission;
use App\Repositories\WritableFolderRepository;

class RoleFolderPermissionObserver
{
    protected $writableFolderRepository;

    public function __construct(WritableFolderRepository $writableFolderRepository)
    {
        $this->writableFolderRepository = $writableFolderRepository;
    }

    public function created(RoleFolderPermission $roleFolderPermission)
    {
        $this->clearCache($roleFolderPermission);
    }

    protected function clearCache(RoleFolderPermission $roleFolderPermission)
    {
        // ロールがnullでない場合にのみ処理を続行
        if ($roleFolderPermission->role) {
            // ロールに関連付けられたユーザーを取得
            $users = $roleFolderPermission->role->users;

            // 各ユーザーのキャッシュをクリア
            foreach ($users as $user) {
                $this->writableFolderRepository->clearWritableFolderCache($user);
                $this->writableFolderRepository->clearReadableFolderCache($user);
            }
        }
    }

    public function updated(RoleFolderPermission $roleFolderPermission)
    {
        $this->clearCache($roleFolderPermission);
    }

    public function deleted(RoleFolderPermission $roleFolderPermission)
    {
        $this->clearCache($roleFolderPermission);
    }
}
