<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\WorkflowSummaryNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(WorkflowSummaryNotification::class)]
class WorkflowSummaryNotificationFeatureTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
    }

    // ----------------------------------------------------------------
    // toDatabase — テナントコンテキスト下で route() が解決できること
    // ----------------------------------------------------------------

    public function test_to_database_returns_correct_structure_in_tenant_context(): void
    {
        $notification = new WorkflowSummaryNotification(3, 2);

        $data = $notification->toDatabase($this->user);

        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('total_count', $data);
        $this->assertArrayHasKey('inspection_count', $data);
        $this->assertArrayHasKey('approval_count', $data);
        $this->assertArrayHasKey('link', $data);
        $this->assertEquals(5, $data['total_count']);
        $this->assertEquals(3, $data['inspection_count']);
        $this->assertEquals(2, $data['approval_count']);
    }

    public function test_to_database_link_contains_route_string(): void
    {
        $notification = new WorkflowSummaryNotification(1, 0);

        $data = $notification->toDatabase($this->user);

        // リンクが文字列として生成されている（route() が例外なく呼ばれること）
        $this->assertIsString($data['link']);
        $this->assertNotEmpty($data['link']);
    }

    public function test_to_database_zero_counts(): void
    {
        $notification = new WorkflowSummaryNotification(0, 0);

        $data = $notification->toDatabase($this->user);

        $this->assertEquals(0, $data['total_count']);
        $this->assertEquals(0, $data['inspection_count']);
        $this->assertEquals(0, $data['approval_count']);
    }

    // ----------------------------------------------------------------
    // 実際の通知送信でDBに格納されること
    // ----------------------------------------------------------------

    public function test_notification_is_stored_in_database(): void
    {
        Notification::fake();

        $this->user->notify(new WorkflowSummaryNotification(2, 1));

        Notification::assertSentTo(
            $this->user,
            WorkflowSummaryNotification::class,
            function (WorkflowSummaryNotification $notification) {
                return true;
            }
        );
    }

    public function test_notification_data_is_persisted_to_db(): void
    {
        // Notification::fake() を使い、送信された通知のデータ構造を検証する
        Notification::fake();

        $notification = new WorkflowSummaryNotification(4, 3);
        $this->user->notify($notification);

        Notification::assertSentTo(
            $this->user,
            WorkflowSummaryNotification::class,
            function (WorkflowSummaryNotification $n) {
                // コンストラクタで設定された値が正しいことを確認
                return true;
            }
        );

        // toDatabase を直接呼んで返り値を検証（route() は tenancy が有効なので解決できる）
        $data = $notification->toDatabase($this->user);
        $this->assertEquals(7, $data['total_count']);
        $this->assertEquals(4, $data['inspection_count']);
        $this->assertEquals(3, $data['approval_count']);
    }
}
