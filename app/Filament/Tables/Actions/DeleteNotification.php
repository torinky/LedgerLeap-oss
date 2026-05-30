<?php

namespace App\Filament\Tables\Actions;

use App\Enums\FolderPermissionType;
use App\Models\RoleFolderPermission;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

class DeleteNotification extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->name('disableNotification');

        $this->label(__('ledger.delete'));

        $this->icon('heroicon-o-bell-slash');

        $this->color('danger');

        // 確認ダイアログの設定
        $this->requiresConfirmation()
            ->modalHeading(__('ledger.disable_notification')) // ダイアログの見出し
            ->modalDescription(__('ledger.disable_notification_confirm')) // 説明文
            ->modalSubmitActionLabel(__('ledger.disable')); // 実行ボタンのラベル

        $this->action(function (Model $record) {
            // $record は Folder モデルと pivot 情報を含むオブジェクト
            // pivot 情報から role_id, folder_id, notification_type_id を取得
            $roleId = $record->pivot->role_id;
            $folderId = $record->pivot->folder_id;
            $notificationTypeId = $record->pivot->notification_type_id;
            // dd($roleId, $folderId, $notificationTypeId);

            // RoleFolderPermission レコードを検索
            $roleFolderPermission = RoleFolderPermission::where('role_id', $roleId)
                ->where('folder_id', $folderId)
                ->whereIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF]);

            if ($roleFolderPermission->count() > 0) {
                // permission を NOTIFY_OFF に更新
                //                $roleFolderPermission->update(['permission' => FolderPermissionType::NOTIFY_OFF]);
                $roleFolderPermission->delete();
            }
        });
    }
}
