<?php

use App\Models\User;
use App\Models\Tenant;
use App\Providers\RouteServiceProvider;
use App\Enums\LoginLandingPage;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $tenant = Tenant::create();
    tenancy()->initialize($tenant);

    // シナリオ1: login_landing_page がデフォルト (my-portal) の場合
    $userMyPortal = User::factory()->create();
    $tenant->users()->attach($userMyPortal);

    $responseMyPortal = $this->post('/login', [
        'email' => $userMyPortal->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $responseMyPortal->assertRedirect('/' . $tenant->getTenantKey() . '/my-portal');
    $this->post('/logout'); // ログアウトして次のシナリオに備える

    // シナリオ2: login_landing_page が Ledgers の場合
    $userLedgers = User::factory()->create(['login_landing_page' => \App\Enums\LoginLandingPage::Ledgers]);
    $tenant->users()->attach($userLedgers);

    $responseLedgers = $this->post('/login', [
        'email' => $userLedgers->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $responseLedgers->assertRedirect('/' . $tenant->getTenantKey() . '/ledger');
});

test('users can not authenticate with invalid password', function () {
    $tenant = Tenant::create();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $tenant = Tenant::create();
    tenancy()->initialize($tenant);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});