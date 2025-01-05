<?php

namespace App\Filament\Resources\Synonym\WordResource\Pages;

use App\Filament\Resources\Synonym\WordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWord extends EditRecord
{
    protected static string $resource = WordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
