<?php

namespace App\Filament\Resources\Synonym\KeywordResource\Pages;

use App\Filament\Resources\Synonym\KeywordResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageKeywords extends ManageRecords
{
    protected static string $resource = KeywordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
