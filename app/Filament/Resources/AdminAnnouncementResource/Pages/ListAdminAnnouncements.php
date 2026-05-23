<?php

namespace App\Filament\Resources\AdminAnnouncementResource\Pages;

use App\Filament\Resources\AdminAnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAdminAnnouncements extends ListRecords
{
    protected static string $resource = AdminAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => AdminAnnouncementResource::canCreate()),
        ];
    }
}
