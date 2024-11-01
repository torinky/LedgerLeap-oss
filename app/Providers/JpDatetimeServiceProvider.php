<?php

namespace App\Providers;

use App\Services\JpDatetimeService;
use Illuminate\Support\ServiceProvider;

class JpDatetimeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('JpDatetime', function () {
            return new JpDatetimeService;
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
