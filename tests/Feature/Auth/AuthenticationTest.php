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
    $roleMyPortal = \Spatie\Permission\Models\Role::create(['name' => 'test-role-portal']);
    $userMyPortal->assignRole($roleMyPortal);
    $tenant->run(function () use ($roleMyPortal, $userMyPortal) {
        $folder = \App\Models\Folder::create(['title' => '/', 'creator_id' => $userMyPortal->id, 'modifier_id' => $userMyPortal->id]);
        \App\Models\RoleFolderPermission::create([
            'role_id' => $roleMyPortal->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::READ,
            'creator_id' => $userMyPortal->id,
            'modifier_id' => $userMyPortal->id,
        ]);
    });

    $responseMyPortal = $this->post('/login', [
        'email' => $userMyPortal->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $responseMyPortal->assertRedirect('/' . $tenant->getTenantKey() . '/my-portal');
    $this->post('/logout'); // ログアウトして次のシナリオに備える

    // シナリオ2: login_landing_page が Ledgers の場合
    $userLedgers = User::factory()->create(['login_landing_page' => \App\Enums\LoginLandingPage::Ledgers]);
    $roleLedgers = \Spatie\Permission\Models\Role::create(['name' => 'test-role-ledgers']);
    $userLedgers->assignRole($roleLedgers);
    $tenant->run(function () use ($roleLedgers, $userLedgers) {
        $folder = \App\Models\Folder::where('title', '/')->first() ?? \App\Models\Folder::create(['title' => '/', 'creator_id' => $userLedgers->id, 'modifier_id' => $userLedgers->id]);
        \App\Models\RoleFolderPermission::create([
            'role_id' => $roleLedgers->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::READ,
            'creator_id' => $userLedgers->id,
            'modifier_id' => $userLedgers->id,
        ]);
    });

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