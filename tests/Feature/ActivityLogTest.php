<?php

namespace tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;
use tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // テスト実行前にシーダーを実行
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // spatieのpermissionのキャッシュをクリアします。
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * 権限のあるユーザーがアクティビティログを閲覧できることをテスト
     *
     * @return void
     */
    public function test_authorized_user_can_view_activity_log(): void
    {
        // 権限を持つユーザーを作成
        $user = User::factory()->create();
        $user->givePermissionTo('view_activity_logs');


        // ユーザーとしてログイン
        $this->actingAs($user);

        // アクティビティログページにアクセス
        $response = $this->get('/activity-log');

        // レスポンスが成功することを確認
        $response->assertStatus(200);

        // レスポンスにアクティビティログが含まれていることを確認
        $response->assertSee(__('ledger.user'));
        $response->assertSee(__('ledger.description'));
        $response->assertSee(__('ledger.created_at'));
        $this->assertCount(1, Activity::all());
    }

    /**
     * 権限のないユーザーがアクティビティログを閲覧できないことをテスト
     *
     * @return void
     */
    public function test_unauthorized_user_cannot_view_activity_log(): void
    {
        // 権限を持たないユーザーを作成
        $user = User::factory()->create();

        // ユーザーとしてログイン
        $this->actingAs($user);

        // アクティビティログページにアクセス
        $response = $this->get('/activity-log');

        // レスポンスが成功することを確認
        $response->assertStatus(200);

        // レスポンスに「閲覧権限がありません。」というメッセージが含まれていることを確認
        $response->assertSee(__('activitylog.no_permission'));
        $this->assertCount(1, Activity::all());
    }

    /**
     * ユーザーが削除された場合の、activityLogのuser表示
     *
     * @return void
     */
    public function test_system_user_is_displayed_when_user_is_deleted(): void
    {
        // ユーザーを作成して、activityを作成
        $user = User::factory()->create();
        activity()->causedBy($user)->log('テスト用アクティビティ');

        // ユーザーを削除
        $user->delete();

        // 権限を持つユーザーを作成
        $user = User::factory()->create();
        $user->givePermissionTo('view_activity_logs');

        // ユーザーとしてログイン
        $this->actingAs($user);

        // アクティビティログページにアクセス
        $response = $this->get('/activity-log');

        // レスポンスが成功することを確認
        $response->assertStatus(200);

        // レスポンスに「システム」が含まれていることを確認
        $response->assertSee('システム');
        $this->assertCount(4, Activity::all());
    }

    /**
     * ページネーションがある場合に確認する。
     *
     * @return void
     */
    public function test_user_can_view_activity_log_with_pagination()
    {
        // 権限を持つユーザーを作成
        $user = User::factory()->create();
        $user->givePermissionTo('view_activity_logs');
        $this->actingAs($user);

        // アクティビティログを作成
        activity()->log('テスト用アクティビティ1');
        activity()->log('テスト用アクティビティ2');
        activity()->log('テスト用アクティビティ3');
        activity()->log('テスト用アクティビティ4');
        activity()->log('テスト用アクティビティ5');
        activity()->log('テスト用アクティビティ6');
        activity()->log('テスト用アクティビティ7');
        activity()->log('テスト用アクティビティ8');
        activity()->log('テスト用アクティビティ9');
        activity()->log('テスト用アクティビティ10');
        activity()->log('テスト用アクティビティ11');

        // アクティビティログページにアクセス
        $response = $this->get('/activity-log');

        // レスポンスが成功することを確認
        $response->assertStatus(200);

        // ページネーションのリンクが存在するか確認する
        $response->assertSeeHtml('<nav role="navigation" aria-label="Pagination Navigation"');
    }

    /**
     * アクティビティログのcauserを確認する。
     *
     * @return void
     */
    public function test_auth_user_can_see_activity_log_of_another_user()
    {
        // ユーザーを作成して、activityを作成
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        activity()->causedBy($user1)->log('テスト用アクティビティ1');

        // 権限を持つユーザーを作成
        $user3 = User::factory()->create();
        $user3->givePermissionTo('view_activity_logs');

        // ユーザーとしてログイン
        $this->actingAs($user3);

        // アクティビティログページにアクセス
        $response = $this->get('/activity-log');

        // レスポンスが成功することを確認
        $response->assertStatus(200);

        // レスポンスに「ユーザー」が含まれていることを確認
        $response->assertSee($user1->name);
        $this->assertCount(4, Activity::all());
    }

    /**
     *  プロパティがあることを確認する。
     *
     * @return void
     */
    public function test_auth_user_can_see_activity_log_with_properties()
    {
        // ユーザーを作成して、activityを作成
        $user = User::factory()->create();
        activity()->performedOn($user)->withProperties(['attributes' => ['name' => 'テストユーザー']])->log('テスト用アクティビティ');

        // 権限を持つユーザーを作成
        $user2 = User::factory()->create();
        $user2->givePermissionTo('view_activity_logs');

        // ユーザーとしてログイン
        $this->actingAs($user2);

        // アクティビティログページにアクセス
        $response = $this->get('/activity-log');

        // レスポンスが成功することを確認
        $response->assertStatus(200);

        // 変更点があることを確認
        $response->assertSee('変更点');
        $this->assertCount(3, Activity::all());
    }
}
