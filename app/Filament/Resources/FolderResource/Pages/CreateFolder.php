<?php

namespace App\Filament\Resources\FolderResource\Pages;

use App\Filament\Resources\FolderResource;
use Filament\Resources\Pages\CreateRecord;

// use CubeAgency\FilamentTreeView\Resources\Pages\CreateTreeViewRecord;

// class CreateFolder extends CreateTreeViewRecord
class CreateFolder extends CreateRecord
{
    protected static string $resource = FolderResource::class;
}
