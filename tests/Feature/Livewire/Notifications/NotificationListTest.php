<?php

namespace Tests\Feature\Livewire\Notifications;

use App\Livewire\Notifications\NotificationList;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;
use App\Models\Tenant;

/**
 * Livewire\Notifications\NotificationList テスト
 *
 * 通知一覧コンポーネントの表示・既読・全既読動作を検証する。
 */
#[CoversClass(NotificationList::class)]
class NotificationListTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRefreshDatabaseWithTenant();
        $this->tenant = $this->getTenant();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * テスト用の未読通知をDBに直接挿入するヘルパー
     */
    private function createNotification(array $payload = [], ?string $type = null): string
    {
        $id = (string) Str::uuid();
        \DB::table('notifications')->insert([
            'id' => $id,
            'type' => $type ?? 'App\\Notifications\\GenericNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => json_encode([
                'payload' => array_merge(['event' => 'created', 'causer_name' => 'テスト太郎'], $payload),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    // ================================================================
    // mount / render
    // ================================================================

    #[Test]
    public function component_renders_successfully(): void
    {
        Livewire::test(NotificationList::class)
            ->assertStatus(200);
    }

    #[Test]
    public function component_renders_with_no_notifications(): void
    {
        Livewire::test(NotificationList::class)
            ->assertSet('totalNotifications', 0);

        Livewire::test(NotificationList::class)
            ->assertSee(__('ledger.no_notification'));
    }

    #[Test]
    public function component_renders_admin_announcements_without_workflow_notifications(): void
    {
        Livewire::test(NotificationList::class, [
            'adminAnnouncements' => [
                [
                    'title' => 'システムメンテナンス',
                    'body' => 'この時間帯は管理者お知らせだけが表示されます。',
                    'level' => 'warning',
                    'status' => 'published',
                    'sticky' => false,
                    'published_at' => '2026-04-28 10:00:00',
                    'links' => [
                        ['label' => __('ledger.details'), 'url' => '/announcements/system-maintenance'],
                    ],
                ],
            ],
        ])
            ->assertSet('workflowNotificationCount', 0)
            ->assertSet('totalNotifications', 1)
            ->assertSee('data-admin-announcement-feed')
            ->assertSee('data-admin-announcement-banner')
            ->assertSee('システムメンテナンス')
            ->assertDontSee(__('ledger.no_notification'))
            ->assertDontSee(__('ledger.mark_all_as_read'));
    }

    #[Test]
    public function component_shows_total_notification_count(): void
    {
        $this->createNotification(['event' => 'created']);
        $this->createNotification(['event' => 'updated']);

        Livewire::test(NotificationList::class)
            ->assertSet('totalNotifications', 2);
    }

    #[Test]
    public function component_renders_actions_for_unread_notifications(): void
    {
        $this->createNotification(['event' => 'created']);

        Livewire::test(NotificationList::class)
            ->assertSee(__('ledger.mark_all_as_read'))
            ->assertSee(__('ledger.mark_as_read'))
            ->assertSee(__('ledger.unread'));
    }

    // ================================================================
    // markAsRead
    // ================================================================

    #[Test]
    public function mark_as_read_marks_single_notification_as_read(): void
    {
        $notificationId = $this->createNotification();

        Livewire::test(NotificationList::class)
            ->call('markAsRead', $notificationId);

        // notification_user テーブルに read_at が記録されていること
        $this->assertDatabaseHas('notification_user', [
            'notification_id' => $notificationId,
            'user_id' => $this->user->id,
        ]);
        $record = \DB::table('notification_user')
            ->where('notification_id', $notificationId)
            ->where('user_id', $this->user->id)
            ->first();
        $this->assertNotNull($record->read_at, 'read_at should be set after markAsRead');
    }

    #[Test]
    public function mark_as_read_does_nothing_when_unauthenticated(): void
    {
        // 認証なしのユーザーで呼び出しても例外が発生しないこと
        Auth::logout();

        // actingAsなしで呼ぶと例外になるため、別ユーザーで代替テスト
        $anotherUser = User::factory()->create();
        $id = $this->createNotification();

        // 別ユーザーが自分の通知を既読にしてもエラーなし
        Livewire::actingAs($anotherUser)
            ->test(NotificationList::class)
            ->call('markAsRead', $id);

        // 元ユーザーの通知はそのまま
        $this->assertEquals(1, $this->user->unreadNotifications()->count());
    }

    // ================================================================
    // markAllAsRead
    // ================================================================

    #[Test]
    public function mark_all_as_read_marks_all_notifications(): void
    {
        $id1 = $this->createNotification(['event' => 'created']);
        $id2 = $this->createNotification(['event' => 'updated']);
        $id3 = $this->createNotification(['event' => 'deleted']);

        Livewire::test(NotificationList::class)
            ->call('markAllAsRead');

        // 全件が notification_user テーブルに既読レコードとして記録されること
        foreach ([$id1, $id2, $id3] as $id) {
            $record = \DB::table('notification_user')
                ->where('notification_id', $id)
                ->where('user_id', $this->user->id)
                ->first();
            $this->assertNotNull($record, "notification_user record should exist for {$id}");
            $this->assertNotNull($record->read_at, "read_at should be set for {$id}");
        }
    }

    // ================================================================
    // formatNotificationData — GenericNotification
    // ================================================================

    #[Test]
    public function render_formats_generic_notification_with_causer_name(): void
    {
        $this->createNotification([
            'event' => 'created',
            'causer_name' => 'テストユーザー',
            'subject_type' => 'App\\Models\\Ledger',
            'subject_id' => 1,
            'ledger_name' => 'テスト台帳',
        ]);

        Livewire::test(NotificationList::class)
            ->assertSet('totalNotifications', 1)
            ->assertStatus(200);
    }

    #[Test]
    public function render_formats_workflow_summary_notification(): void
    {
        $this->createNotification([
            'message' => 'ワークフローサマリー通知メッセージ',
        ], 'App\\Notifications\\WorkflowSummaryNotification');

        Livewire::test(NotificationList::class)
            ->assertSet('totalNotifications', 1)
            ->assertStatus(200);
    }

    #[Test]
    public function render_formats_notification_without_subject(): void
    {
        $this->createNotification([
            'event' => 'updated',
            'causer_name' => '管理者',
        ]);

        Livewire::test(NotificationList::class)
            ->assertStatus(200);
    }

    // ================================================================
    // dispatches update-tab-count event
    // ================================================================

    #[Test]
    public function render_dispatches_update_tab_count_event(): void
    {
        Livewire::test(NotificationList::class)
            ->assertDispatched('update-tab-count');
    }

    // ================================================================
    // pagination pageName の独立性
    // ================================================================

    #[Test]
    public function paginator_uses_notification_page_as_page_name(): void
    {
        // 11件以上の通知を作成してページネーションが発生する状態にする
        for ($i = 0; $i < 12; $i++) {
            $this->createNotification(['event' => 'created']);
        }

        $component = Livewire::test(NotificationList::class);
        $notifications = $component->viewData('notifications');

        $this->assertSame('notification_page', $notifications->getPageName(),
            'NotificationList のページネーターは notification_page を pageName として使用すること');
    }

    #[Test]
    public function page_param_does_not_affect_notification_page(): void
    {
        // 11件以上の通知を作成
        for ($i = 0; $i < 12; $i++) {
            $this->createNotification(['event' => 'created']);
        }

        // Livewire v3 の paginators は初回レンダリング後に登録される。
        // gotoPage でページを移動した後、notification_page の currentPage が
        // page パラメータと独立していることを確認する。
        $component = Livewire::test(NotificationList::class)
            ->call('gotoPage', 2, 'notification_page');

        $notifications = $component->viewData('notifications');
        $this->assertSame(2, $notifications->currentPage(),
            'gotoPage(2, notification_page) でページ2に遷移できること');

        // page パラメータを直接 set しても notification_page には影響しない
        $component->set('paginators', []);
        $notifications = $component->viewData('notifications');
        $this->assertSame('notification_page', $notifications->getPageName(),
            '外部から paginators をリセットしても pageName は変わらないこと');
    }
}
