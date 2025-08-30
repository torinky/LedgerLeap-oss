<?php

use App\Models\Tenant;
use App\Providers\RouteServiceProvider;

it('returns a successful response', function () {
    $tenant = Tenant::create();
    tenancy()->initialize($tenant);

    $response = $this->get('/');

    $response->assertRedirect('/' . $tenant->getTenantKey() . RouteServiceProvider::HOME);
});