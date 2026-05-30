<?php

namespace Tests\Support;

use App\Providers\AuthServiceProvider;
use Illuminate\Http\Request;

class TestAuthServiceProvider extends AuthServiceProvider
{
    /**
     * Register the request rebind handler.
     *
     * @return void
     */
    protected function registerRequestRebindHandler()
    {
        // Do nothing to prevent setUserResolver from being called
        // This is to avoid Mockery expectations issues in tests
    }
}
