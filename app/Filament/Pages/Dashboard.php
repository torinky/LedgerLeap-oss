<?php

namespace App\Filament\Pages;

use App\Models\Folder;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static bool $shouldRegisterNavigation = false;

    public ?string $fromTenant = null;

    public function mount(): void
    {
        $this->fromTenant = request()->query('from_tenant');
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\DashboardLinksWidget::class,
        ];
    }

    //    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    //    protected static string $view = 'filament.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? __('ledger.setting');
    }

    protected function getHeaderActions(): array
    {
        return [
/*            Action::make('updateModel')
                ->label(__('ledger.folder.fix'))
                ->action('fixFolderTree')
                ->icon('heroicon-o-wrench')
                ->color('info'),*/
        ];
    }

    public function fixFolderTree()
    {
        try {
            Folder::fixtree();
            Notification::make()
                ->title(__('ledger.success'))
                ->body(__('ledger.action_success'))
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(__('ledger.error'))
                ->body(__('ledger.action_error') . ': ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
