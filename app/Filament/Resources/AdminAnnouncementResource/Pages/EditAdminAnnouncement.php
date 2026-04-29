<?php

namespace App\Filament\Resources\AdminAnnouncementResource\Pages;

use App\Filament\Resources\AdminAnnouncementResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditAdminAnnouncement extends EditRecord
{
    protected static string $resource = AdminAnnouncementResource::class;

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $link = $data['links'][0] ?? [];

        $data['status'] = $this->record->displayStatusKey();
        $data['scope'] = AdminAnnouncementResource::normalizeScopeSelection($data['scope'] ?? ['current_tenant']);
        $data['cta_label'] = $link['label'] ?? null;
        $data['cta_url'] = $link['url'] ?? null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['status'] = $this->record->status;
        $data['scope'] = AdminAnnouncementResource::normalizeScopeSelection($data['scope'] ?? ['current_tenant']);
        $data['links'] = AdminAnnouncementResource::toLinksPayload($data);
        unset($data['cta_label'], $data['cta_url']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('saveDraft')
                ->label(__('ledger.admin_announcement_banner_save_draft_action'))
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->action(fn() => $this->save()),
            Actions\Action::make('publishAnnouncement')
                ->label(__('ledger.admin_announcement_banner_publish_action'))
                ->icon('heroicon-o-megaphone')
                ->color('success')
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'published',
                        'published_at' => $this->record->published_at ?? now(),
                    ]);

                    Notification::make()
                        ->title(__('ledger.success'))
                        ->body(__('ledger.admin_announcement_banner_published'))
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
            Actions\Action::make('archiveAnnouncement')
                ->label(__('ledger.admin_announcement_banner_archive_action'))
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'archived',
                    ]);

                    Notification::make()
                        ->title(__('ledger.success'))
                        ->body(__('ledger.admin_announcement_banner_archived'))
                        ->success()
                        ->send();

                    $this->fillForm();
                }),
        ];
    }
}
