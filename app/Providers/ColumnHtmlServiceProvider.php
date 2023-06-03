<?php

namespace App\Providers;

use App\Services\Ledger\ColumnHtml;
use Illuminate\Support\ServiceProvider;

class ColumnHtmlServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('ColumnHtml', function () {
            return new ColumnHtml();
        });

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
