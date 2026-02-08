<?php

namespace App\Providers;

use App\Database\MySqlConnection;
use App\Models\AutoLink;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Modules\ImageUpload\ImageManagerInterface;
use App\Modules\ImageUpload\LocalImageManager;
use App\Models\RoleFolderPermission;
use App\Observers\AutoLinkObserver;
use App\Observers\FolderObserver;
use App\Observers\LedgerDefineObserver;
use App\Observers\LedgerDiffObserver;
use App\Observers\LedgerObserver;
use App\Observers\RoleFolderPermissionObserver;
use App\Observers\UserPermissionsObserver;
use App\Services\TenantAccessService;
use Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider;
use Filament\Support\Facades\FilamentView;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

// use App\Modules\ImageUpload\CloudinaryImageManager;

// use Cloudinary\Cloudinary;

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

        // TenantAccessServiceをシングルトンとして登録
        $this->app->singleton(TenantAccessService::class, function ($app) {
            return new TenantAccessService;
        });

        $this->app->singleton(\App\Services\EmbeddingService::class, function ($app) {
            return new \App\Services\EmbeddingService;
        });

        $this->app->singleton(\Vaites\ApacheTika\Client::class, function ($app) {
            return \Vaites\ApacheTika\Client::make('tika', 9998);
        });

        //        }

        $this->setCustomResolverForMySql();

        /*        FilamentView::registerRenderHook(
                    'panels::head.end',
                    fn(): string => Blade::render('@vite([\'resources/css/app.css\', \'resources/js/app.js\'])'),
                );*/
    }

    /**
     * @return void
     */
    private function setCustomResolverForMySql()
    {
        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            return new MySqlConnection($connection, $database, $prefix, $config);
        });
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Log::info("AppServiceProvider booting...");
        AutoLink::observe(AutoLinkObserver::class);
        Folder::observe(FolderObserver::class);
        LedgerDiff::observe(LedgerDiffObserver::class);
        Ledger::observe(LedgerObserver::class);
        LedgerDefine::observe(LedgerDefineObserver::class);

        User::observe(UserPermissionsObserver::class);
        Role::observe(UserPermissionsObserver::class);
        Organization::observe(UserPermissionsObserver::class);
        RoleFolderPermission::observe(RoleFolderPermissionObserver::class);

        // Domain モデルが作成される際にUUIDを自動生成
        Domain::creating(function (Domain $domain) {
            $domain->id = $domain->id ?? (string) Str::uuid();
        });

        /*        Livewire::listen('component.hydrate', function ($component) {
                    dd($component);
                    if (tenancy()->initialized) {
                        $component->snapshot['memo']['tenant_id'] = tenant('id');
                    }
                });*/

        // InitializeTenancyByRequestData の設定を変更
        InitializeTenancyByRequestData::$header = null;
        InitializeTenancyByRequestData::$queryParameter = 'snapshot.memo.tenant_id';
    }
}
