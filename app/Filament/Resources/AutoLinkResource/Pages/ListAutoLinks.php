<?php

namespace App\Filament\Resources\AutoLinkResource\Pages;

use App\Filament\Resources\AutoLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAutoLinks extends ListRecords
{
    protected static string $resource = AutoLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
