<?php

namespace App\Filament\Resources\FolderResource\Pages;

use App\Filament\Resources\FolderResource;
use CubeAgency\FilamentTreeView\Resources\Pages\TreeViewRecords;
use Filament\Actions;

class ListFolders extends TreeViewRecords
{
    protected static string $resource = FolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getRowTitle($row): ?string
    {
        return $row->getAttribute('title');
    }
}
