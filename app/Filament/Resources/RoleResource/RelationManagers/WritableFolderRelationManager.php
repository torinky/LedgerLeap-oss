<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;

class WritableFolderRelationManager extends FolderRelationManager
{
    protected static string $relationship = 'writableFolders';

    protected FolderPermissionType $permission = FolderPermissionType::WRITE;
}
