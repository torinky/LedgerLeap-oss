<?php

namespace App\Filament\Resources\FolderResource\Pages;

use App\Filament\Resources\FolderResource;
use CubeAgency\FilamentTreeView\Resources\Pages\CreateTreeViewRecord;

class CreateFolder extends CreateTreeViewRecord
{
    protected static string $resource = FolderResource::class;
}
