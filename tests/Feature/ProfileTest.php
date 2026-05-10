<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

// このテストファイル内のすべてのテストでテナントコンテキストを扱うため、
// beforeEachフックで共通のセットアップを行う。
beforeEach(function () {
    // テナントを作成し、初期化する
    $tenant = Tenant::factory()->create();
    tenancy()->initialize($tenant);

    // テナントルートのURLを正しく生成するために、URLジェネレータに
    // 'tenant' パラメータのデフォルト値を設定する。
    // これにより、route('tenant.route.name') のような呼び出しで
    // 'tenant' パラメータを省略しても、自動的に現在のテナントIDが使われる。
    URL::defaults(['tenant' => $tenant->getTenantKey()]);

    // テストで使用するユーザーを作成し、プロパティとして保持する
    $this->user = User::factory()->create();
});

test('profile page is displayed', function () {
    $response = $this
        ->actingAs($this->user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $response = $this
        ->actingAs($this->user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'login_landing_page' => 'ledgers',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->user->refresh();

    $this->assertSame('Test User', $this->user->name);
    $this->assertSame('test@example.com', $this->user->email);
    $this->assertNull($this->user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $response = $this
        ->actingAs($this->user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $this->user->email,
            'login_landing_page' => 'ledgers',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($this->user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $response = $this
        ->actingAs($this->user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertSoftDeleted($this->user);
    //    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $response = $this
        ->actingAs($this->user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($this->user->fresh());
});
