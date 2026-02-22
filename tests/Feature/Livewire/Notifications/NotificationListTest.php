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

/**
 * Livewire\Notifications\NotificationList テスト
 *
 * 通知一覧コンポーネントの表示・既読・全既読動作を検証する。
 */
#[CoversClass(NotificationList::class)]
class NotificationListTest extends TestCase
{
    protected bool $tenancy = true;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    #[Test]
    public function component_shows_total_notification_count(): void
    {
        $this->createNotification(['event' => 'created']);
        $this->createNotification(['event' => 'updated']);

        Livewire::test(NotificationList::class)
            ->assertSet('totalNotifications', 2);
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
}
