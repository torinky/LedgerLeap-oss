<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;

class ReadableFolderRelationManager extends FolderRelationManager
{
    protected static string $relationship = 'readableFolders';

    protected FolderPermissionType $permission = FolderPermissionType::READ;
}
