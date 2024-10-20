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

    //    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    //    protected static string $view = 'filament.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? __('ledger.setting');
    }

    protected function getActions(): array
    {
        return [
            Action::make('updateModel')
                ->label(__('ledger.folder.fix'))
                ->action('fixFolderTree')
                ->icon('heroicon-o-wrench')
                ->color('info'),
        ];
    }

    public function fixFolderTree()
    {
        try {
            Folder::fixtree();
            Notification::make()
                ->title('成功')
                ->body('アクションが正常に実行されました。')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('エラー')
                ->body('アクションの実行に失敗しました: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

}
