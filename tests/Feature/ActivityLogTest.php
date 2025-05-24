<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // --- 追加: アクティビティログを毎回クリア ---
    Activity::query()->delete();
    // アクティビティロギングを無効化
    activity()->disableLogging();


    Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // アクティビティロギングを有効化
    activity()->enableLogging();
});

function getWithRedirectFollow($url)
{
    $response = get($url);
    if ($response->isRedirection()) {
        $response = test()->followingRedirects()->get($url);
    }
    return $response;
}

it('権限のあるユーザーがアクティビティログを閲覧できる', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view_activity_logs');
    actingAs($user);

    activity()->log('test log');

    $response = getWithRedirectFollow('/activity-log');
    $response->assertStatus(200);
    $response->assertSee(__('ledger.user'));
    $response->assertSee(__('ledger.description'));
    $response->assertSee(__('ledger.created_at'));

    expect(Activity::count())->toBe(2);
});

it('権限の無いユーザーはアクティビティログを閲覧できない', function () {
    $user = User::factory()->create();
    actingAs($user);

    $response = getWithRedirectFollow('/activity-log');
    $response->assertStatus(200);
    $response->assertSee(__('activitylog.no_permission'));

    expect(Activity::count())->toBe(1);
});

it('ユーザー削除後はシステム表記となる', function () {
    $user = User::factory()->create();
    activity()->causedBy($user)->log('ユーザー削除テスト用アクティビティ');
    $user->delete();

    $admin = User::factory()->create();
    $admin->givePermissionTo('view_activity_logs');
    actingAs($admin);

    $response = getWithRedirectFollow('/activity-log');
    $response->assertStatus(200);
    $response->assertSee('システム');

    expect(Activity::count())->toBe(4);
});

it('ページネーション付きでアクティビティログを閲覧できる', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('view_activity_logs');
    actingAs($user);

    for ($i = 1; $i <= 11; $i++) {
        activity()->log("テスト用アクティビティ{$i}");
    }

    $response = getWithRedirectFollow('/activity-log');
    $response->assertStatus(200);
    $response->assertSee('<nav role="navigation" aria-label="Pagination Navigation"', false);

    expect(Activity::count())->toBe(12);
});

it('他ユーザーのアクティビティログも参照できる', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    activity()->causedBy($user1)->log('他ユーザーのアクティビティ');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view_activity_logs');
    actingAs($viewer);

    $response = getWithRedirectFollow('/activity-log');
    $response->assertStatus(200);
    $response->assertSee($user1->name);

    expect(Activity::count())->toBe(4);
});

it('アクティビティのプロパティも参照できる', function () {
    $user = User::factory()->create();
    activity()
        ->performedOn($user)
        ->withProperties(['attributes' => ['name' => 'テストユーザー']])
        ->log('属性付きアクティビティ');

    $viewer = User::factory()->create();
    $viewer->givePermissionTo('view_activity_logs');
    actingAs($viewer);

    $response = getWithRedirectFollow('/activity-log');
    $response->assertStatus(200);
    $response->assertSee('変更点');

    expect(Activity::count())->toBe(3);
});