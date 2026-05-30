<?php

use App\Models\Tenant;

it('returns a successful response', function () {
    $tenant = Tenant::create();
    tenancy()->initialize($tenant);

    $response = $this->get('/');

    $response->assertRedirect('/login');
});
