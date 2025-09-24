<?php

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\TokensRelationManager;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class); // RefreshDatabase トレイトを使用

// テストのセットアップ
// 各テストの実行前に、管理者ユーザーと一般ユーザーを作成し、管理者としてログインする
beforeEach(function () {
    // データベースシーダーを実行してロールと権限を作成
    $this->seed();

    // 管理者ユーザーを作成してログイン
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin'); // 正しいロール名を指定
    actingAs($this->admin);

    // テスト対象の一般ユーザーを作成
    $this->user = User::factory()->create();
});

it('can render the tokens relation manager', function () {
    Livewire::test(TokensRelationManager::class, [
        'ownerRecord' => $this->user,
        'pageClass' => EditUser::class,
    ])->assertSuccessful();
});

it('can create a token', function () {
    Livewire::test(TokensRelationManager::class, [
        'ownerRecord' => $this->user,
        'pageClass' => EditUser::class,
    ])
    ->callTableAction('create', data: [
        'name' => 'test-token',
    ])
    ->assertNotified(__('admin.api_token_created'));

    // データベースにトークンが正しく保存されたか確認
    assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $this->user->id,
        'name' => 'test-token',
    ]);
});

it('can list tokens', function () {
    // テスト用のトークンを作成
    $token = $this->user->tokens()->create(['name' => 'list-test-token', 'token' => 'dummy', 'abilities' => ['*']]);

    Livewire::test(TokensRelationManager::class, [
        'ownerRecord' => $this->user,
        'pageClass' => EditUser::class,
    ])
    ->assertCanSeeTableRecords(new Collection([$token]));
});

it('can delete a token', function () {
    // テスト用のトークンを作成
    $token = $this->user->tokens()->create(['name' => 'delete-test-token', 'token' => 'dummy', 'abilities' => ['*']]);

    Livewire::test(TokensRelationManager::class, [
        'ownerRecord' => $this->user,
        'pageClass' => EditUser::class,
    ])
    ->callTableAction('delete', $token);

    // データベースからトークンが削除されたか確認
    assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->id,
    ]);
});
