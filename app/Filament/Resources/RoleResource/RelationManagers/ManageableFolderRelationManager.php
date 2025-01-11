<?php

namespace App\Filament\Resources\RoleResource\RelationManagers;

use App\Enums\FolderPermissionType;

class ManageableFolderRelationManager extends FolderRelationManager
{
    protected static string $relationship = 'manageableFolders';

    protected FolderPermissionType $permission = FolderPermissionType::ADMIN;
}
