<?php

namespace App\Filament\Resources\Synonym\TechnicalTermGroupResource\Pages;

use App\Filament\Resources\Synonym\TechnicalTermGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTechnicalTermGroup extends EditRecord
{
    protected static string $resource = TechnicalTermGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill($data): array
    {
        // もし$data内に'synonyms'が存在する場合
        if (isset($data['synonyms'])) {
            $synonyms = [];

            foreach ($data['synonyms'] as $synonym) {
                $synonyms[] = ['synonym' => $synonym];
            }

            $data['synonyms'] = $synonyms;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // もし$data内に'synonyms'が存在する場合
        if (isset($data['synonyms'])) {
            $synonyms = collect($data['synonyms'])->pluck('synonym')->toArray();
            $data['synonyms'] = $synonyms;
        }
        $data['modifier_id'] = auth()->id();

        return $data;
    }

}
