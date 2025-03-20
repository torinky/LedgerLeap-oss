<?php

namespace App\Filament\Resources\FolderResource\Pages;

use App\Filament\Resources\FolderResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

//use CubeAgency\FilamentTreeView\Resources\Pages\TreeViewRecords;

//class ListFolders extends TreeViewRecords
class ListFolders extends ListRecords
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
