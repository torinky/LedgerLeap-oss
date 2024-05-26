<?php

namespace App\Filament\Resources\Synonym\LedgerWordResource\Pages;

use App\Filament\Resources\Synonym\LedgerWordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLedgerWords extends ListRecords
{
    protected static string $resource = LedgerWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
