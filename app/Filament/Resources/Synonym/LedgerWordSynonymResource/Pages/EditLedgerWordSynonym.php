<?php

namespace App\Filament\Resources\Synonym\LedgerWordSynonymResource\Pages;

use App\Filament\Resources\Synonym\LedgerWordSynonymResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLedgerWordSynonym extends EditRecord
{
    protected static string $resource = LedgerWordSynonymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
