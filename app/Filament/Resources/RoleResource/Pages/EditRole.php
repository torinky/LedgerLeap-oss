<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Enums\FolderPermissionType;
use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /*    protected function afterSave(): void
        {
            // データ保存前の処理
            $data = $this->data;
            $scopeFolders = $data['readable folders'] ?? [];
            $writableFolders = $data['writable folders'] ?? [];
            $permissions = $this->getPermissions($scopeFolders, $writableFolders);
            //        dd($this->data, $permissions);
            $this->record->folderPermissions()->sync($permissions);

        }

        private function getPermissions(array $scopeFolders, array $writableFolders): array
        {
            $allState = array_unique(array_merge(
                $scopeFolders,
                $writableFolders
            ));
            $permissions = [];
            foreach ($allState as $folderId) {
                $permissions[$folderId] = [
                    'role_id' => $this->record->id,
                    'folder_id' => $folderId,
                    'modifier_id' => auth()->user()->id,
                    'permission' => in_array($folderId, $writableFolders, true) ? FolderPermissionType::WRITE : FolderPermissionType::READ,
                ];
            }

            return $permissions;
        }*/
}
