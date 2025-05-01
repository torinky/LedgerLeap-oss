<?php

namespace App\Providers;

use App\Database\MySqlConnection;
use App\Models\LedgerDiff;
use App\Modules\ImageUpload\ImageManagerInterface;
use App\Modules\ImageUpload\LocalImageManager;
use App\Observers\LedgerDiffObserver;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

//use App\Modules\ImageUpload\CloudinaryImageManager;

//use Cloudinary\Cloudinary;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        /*        $this->app->bind(Cloudinary::class, function () {
                    return new Cloudinary([
                        'cloud' => [
                            'cloud_name' => config('cloudinary.cloud_name'),
                            'api_key' => config('cloudinary.api_key'),
                            'api_secret' => config('cloudinary.api_secret'),
                        ],
                    ]);
                });
                if ($this->app->environment('production')) {
                    $this->app->bind(ImageManagerInterface::class, CloudinaryImageManager::class);
                } else {*/
        $this->app->bind(ImageManagerInterface::class, LocalImageManager::class);
        $this->app->register(IdeHelperServiceProvider::class);
        //        }

        $this->setCustomResolverForMySql();

        FilamentView::registerRenderHook(
            'panels::head.end',
            fn(): string => Blade::render('@vite([\'resources/css/app.css\', \'resources/js/app.js\'])'),
        );
    }

    /**
     * @return void
     */
    private function setCustomResolverForMySql()
    {
        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            return new MysqlConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
