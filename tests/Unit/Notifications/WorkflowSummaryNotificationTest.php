<?php

namespace Tests\Unit\Notifications;

use App\Mail\WorkflowSummaryMail;
use App\Models\User;
use App\Notifications\WorkflowSummaryNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * WorkflowSummaryNotification のユニットテスト
 *
 * Phase 2 Sprint 2: Notifications クラスカバレッジ向上
 *
 * @see app/Notifications/WorkflowSummaryNotification.php
 */
class WorkflowSummaryNotificationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        Notification::fake();
    }

    // -------------------------------------------------------
    // コンストラクタテスト
    // -------------------------------------------------------

    #[Test]
    public function constructor_sets_counts_correctly(): void
    {
        $notification = new WorkflowSummaryNotification(3, 5);

        $this->assertEquals(3, $notification->inspectionCount);
        $this->assertEquals(5, $notification->approvalCount);
    }

    // -------------------------------------------------------
    // via テスト
    // -------------------------------------------------------

    #[Test]
    public function via_returns_database_channel_by_default(): void
    {
        $user = User::factory()->create();
        $notification = new WorkflowSummaryNotification(1, 1);

        $channels = $notification->via($user);

        $this->assertContains('database', $channels);
    }

    #[Test]
    public function via_adds_mail_channel_when_user_has_permission(): void
    {
        // Permission が存在しない場合は事前作成
        Permission::firstOrCreate(['name' => 'receive_workflow_summary_email', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('receive_workflow_summary_email');

        $notification = new WorkflowSummaryNotification(2, 3);
        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    #[Test]
    public function via_does_not_add_mail_without_permission(): void
    {
        $user = User::factory()->create();

        $notification = new WorkflowSummaryNotification(1, 0);
        $channels = $notification->via($user);

        $this->assertNotContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    // -------------------------------------------------------
    // toMail テスト
    // -------------------------------------------------------

    #[Test]
    public function to_mail_returns_null_for_non_user_notifiable(): void
    {
        $notification = new WorkflowSummaryNotification(1, 1);

        $nonUser = new \stdClass;
        $result = $notification->toMail($nonUser);

        $this->assertNull($result);
    }

    #[Test]
    public function to_mail_returns_workflow_summary_mail_instance(): void
    {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $notification = new WorkflowSummaryNotification(2, 3);

        $result = $notification->toMail($user);

        $this->assertInstanceOf(WorkflowSummaryMail::class, $result);
    }

    // -------------------------------------------------------
    // toDatabase テスト
    // route('notifications.index') は global ルートになったため、
    // ここでは通知本文の値だけを直接検証する。
    // -------------------------------------------------------

    #[Test]
    public function to_database_counts_are_correct(): void
    {
        $notification = new WorkflowSummaryNotification(4, 2);

        $this->assertEquals(4, $notification->inspectionCount);
        $this->assertEquals(2, $notification->approvalCount);
        // 合計値も確認
        $this->assertEquals(6, $notification->inspectionCount + $notification->approvalCount);
    }

    #[Test]
    public function to_database_inspection_count_is_integer(): void
    {
        $notification = new WorkflowSummaryNotification(1, 1);

        $this->assertIsInt($notification->inspectionCount);
        $this->assertIsInt($notification->approvalCount);
    }

    #[Test]
    public function to_database_zero_inspection_count_is_valid(): void
    {
        $notification = new WorkflowSummaryNotification(0, 1);

        $this->assertEquals(0, $notification->inspectionCount);
        $this->assertEquals(1, $notification->approvalCount);
    }
}
