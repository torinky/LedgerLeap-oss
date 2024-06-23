<?php

namespace App\Filament\Resources\Synonym\TechnicalTermGroupResource\Pages;

use App\Filament\Resources\Synonym\TechnicalTermGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTechnicalTermGroups extends ListRecords
{
    protected static string $resource = TechnicalTermGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
