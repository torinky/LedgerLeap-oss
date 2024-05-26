<?php

namespace App\Filament\Resources\Synonym\LedgerWordResource\Pages;

use App\Filament\Resources\Synonym\LedgerWordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLedgerWord extends EditRecord
{
    protected static string $resource = LedgerWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
