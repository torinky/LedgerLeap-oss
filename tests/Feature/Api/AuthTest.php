<?php

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stancl\Tenancy\Facades\Tenancy;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

// `RefreshDatabase` は Pest.php でグローバルに適用されているため、ここでは不要

beforeEach(function () {
    // テナントとドメインを作成
    $tenant = Tenant::create();
    $tenant->domains()->create(['domain' => 'localhost']);
    Tenancy::initialize($tenant);
});

test('unauthenticated user cannot access protected user route', function () {
    // /api/user ルートに認証なしでアクセス
    getJson('/api/user')
        ->assertStatus(401);
});

test('unauthenticated user cannot access protected search route', function () {
    // /api/v1/search ルートに認証なしでアクセス
    getJson('/api/v1/search')
        ->assertStatus(401);
});

test('user can authenticate and get user info', function () {
    // テスト用のユーザーを作成
    $user = User::factory()->create();

    // actingAs ヘルパーで認証済みユーザーとしてAPIリクエストを送信
    $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

    // レスポンスの検証
    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

test('user can create api tokens and use them', function () {
    // テスト用のユーザーを作成
    $user = User::factory()->create();

    // APIトークンを作成
    $token = $user->createToken('test-token')->plainTextToken;

    // 作成したトークンを使ってAPIにアクセス
    $response = getJson('/api/user', [
        'Authorization' => 'Bearer ' . $token,
    ]);

    // レスポンスの検証
    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
        ]);
});

test('invalid token is rejected', function () {
    // 不正なトークンでAPIにアクセス
    $response = getJson('/api/user', [
        'Authorization' => 'Bearer invalid-token',
    ]);

    // レスポンスの検証
    $response->assertStatus(401);
});