<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function mount(): void
    {
        parent::mount();

        $expiredCount = \App\Models\User::where('ignore_ad_org_sync_until', '<=', now())->count();

        if ($expiredCount > 0) {
            \Filament\Notifications\Notification::make()
                ->title(__('ledger.manual_sync_expired_warning_title'))
                ->body(__('ledger.manual_sync_expired_warning_body', ['count' => $expiredCount]))
                ->persistent()
                ->warning()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('filter')
                        ->button()
                        ->label(__('ledger.filter_expired_users'))
                        ->url(UserResource::getUrl('index', ['tableFilters[manual_sync_status][status]' => 'expired'])),
                ])
                ->send();
        }
    }
}
