<?php

namespace App\Filament\Resources\Synonym\WordResource\Pages;

use App\Filament\Resources\Synonym\WordResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWords extends ManageRecords
{
    protected static string $resource = WordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
