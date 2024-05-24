<?php

namespace App\Filament\Resources\Synonym\TansiResource\Pages;

use App\Filament\Resources\Synonym\TansiResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageTansis extends ManageRecords
{
    protected static string $resource = TansiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
