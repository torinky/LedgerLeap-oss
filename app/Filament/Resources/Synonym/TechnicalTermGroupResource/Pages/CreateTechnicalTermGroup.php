<?php

namespace App\Filament\Resources\Synonym\TechnicalTermGroupResource\Pages;

use App\Filament\Resources\Synonym\TechnicalTermGroupResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTechnicalTermGroup extends CreateRecord
{
    protected static string $resource = TechnicalTermGroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // もし$data内に'synonyms'が存在する場合
        if (isset($data['synonyms'])) {
            $synonyms = collect($data['synonyms'])->pluck('synonym')->toArray();
            $data['synonyms'] = $synonyms;
        }
        $data['creator_id'] = auth()->id();
        $data['modifier_id'] = auth()->id();

        return $data;
    }
}
