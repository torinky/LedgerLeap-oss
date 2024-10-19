<?php

namespace App\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends \Filament\Pages\Dashboard
{
    public static bool $shouldRegisterNavigation = false;

//    protected static ?string $navigationIcon = 'heroicon-o-document-text';

//    protected static string $view = 'filament.pages.dashboard';

    public function getTitle(): string|Htmlable
    {
        return static::$title ?? __('ledger.settings');
    }
}
