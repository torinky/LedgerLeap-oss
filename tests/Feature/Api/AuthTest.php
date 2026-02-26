<?php

use App\Models\Tenant;
use App\Models\User;
use Stancl\Tenancy\Facades\Tenancy;

use function Pest\Laravel\getJson;

// グローバルテナントでテナント初期化のコストを削減
$globalTenant = null;

beforeEach(function () use (&$globalTenant) {
    // 既存のテナントを再利用して初期化時間を短縮
    if ($globalTenant === null) {
        $globalTenant = Tenant::firstOrCreate(
            ['id' => 'auth-test-tenant'],
            ['id' => 'auth-test-tenant']
        );

        // ドメインも再利用
        if (! $globalTenant->domains()->where('domain', 'localhost')->exists()) {
            $globalTenant->domains()->create(['domain' => 'localhost']);
        }
    }

    Tenancy::initialize($globalTenant);
});

test('unauthenticated user cannot access protected user route', function () {
    getJson('/api/user')->assertStatus(401);
});

test('unauthenticated user cannot access protected search route', function () {
    getJson('/api/v1/search')->assertStatus(401);
});

test('user can authenticate and get user info', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/user')
        ->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

test('user can create api tokens and use them', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;

    getJson('/api/user', [
        'Authorization' => 'Bearer '.$token,
    ])->assertStatus(200)->assertJson(['id' => $user->id]);
});

test('invalid token is rejected', function () {
    getJson('/api/user', [
        'Authorization' => 'Bearer invalid-token',
    ])->assertStatus(401);
});
