<?php

declare(strict_types=1);

namespace Studio15\FilamentTree;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Studio15\FilamentTree\Commands\MakeTreePageCommand;
use Studio15\FilamentTree\Components\Footer;
use Studio15\FilamentTree\Components\Header;
use Studio15\FilamentTree\Components\Move;
use Studio15\FilamentTree\Components\Row;

/**
 * FilamentTree Plugin Service Provider
 *
 * @author 15web.ru <info@15web.ru>
 */
final class FilamentTreeServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-tree';

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name(self::$name)
            ->hasCommands([
                MakeTreePageCommand::class,
            ])
            ->hasViews()
            ->hasConfigFile(self::$name)
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        Livewire::component('filament-tree::move', Move::class);
        Livewire::component('filament-tree::row', Row::class);
        Livewire::component('filament-tree::header', Header::class);
        Livewire::component('filament-tree::footer', Footer::class);

        FilamentAsset::register([
            Css::make('filament-tree', __DIR__.'/../resources/dist/filament-tree.min.css')->loadedOnRequest(),
            Js::make('filament-tree', __DIR__.'/../resources/dist/filament-tree.min.js')->loadedOnRequest(),
            Js::make('sortable', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js')->loadedOnRequest(),
        ], package: self::$name);
    }
}
