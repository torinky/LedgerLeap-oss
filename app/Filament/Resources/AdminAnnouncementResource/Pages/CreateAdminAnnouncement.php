<?php

namespace App\Filament\Resources\AdminAnnouncementResource\Pages;

use App\Filament\Resources\AdminAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateAdminAnnouncement extends CreateRecord
{
    protected static string $resource = AdminAnnouncementResource::class;

    public function getMaxContentWidth(): Width | string | null
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = $data['status'] ?? 'draft';
        $data['scope'] = AdminAnnouncementResource::normalizeScopeSelection($data['scope'] ?? ['current_tenant']);
        $data['links'] = AdminAnnouncementResource::toLinksPayload($data);
        unset($data['cta_label'], $data['cta_url']);

        return $data;
    }
}