<?php

namespace Tests\Unit\Notifications;

use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\NotificationType;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * GenericNotification のユニットテスト
 *
 * Phase 2 Sprint 2: Notifications クラスカバレッジ向上
 *
 * @see app/Notifications/GenericNotification.php
 */
class GenericNotificationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        Notification::fake();
    }

    // -------------------------------------------------------
    // コンストラクタ / via テスト
    // -------------------------------------------------------

    #[Test]
    public function via_returns_database_channel_by_default(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'generic_event',
            'model' => Ledger::class,
            'event' => 'generic_event',
        ]);
        $notifiable = User::factory()->create();

        $notification = new GenericNotification(
            $notificationType->id,
            $ledger,
        );

        $channels = $notification->via($notifiable);

        $this->assertContains('database', $channels);
    }

    #[Test]
    public function via_returns_only_database_for_non_user_notifiable(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'inspection_requested',
            'model' => Ledger::class,
            'event' => 'inspection_requested',
        ]);

        $notification = new GenericNotification($notificationType->id, $ledger);

        // User 以外の notifiable（標準オブジェクト）
        $nonUser = new class
        {
            public int $id = 0;
        };

        $channels = $notification->via($nonUser);

        $this->assertEquals(['database'], $channels);
    }

    #[Test]
    public function via_adds_mail_channel_for_workflow_notification_with_permission(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'approved',
            'model' => Ledger::class,
            'event' => 'approved',
        ]);

        Permission::firstOrCreate(['name' => 'receive_workflow_action_email', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('receive_workflow_action_email');

        $notification = new GenericNotification($notificationType->id, $ledger);
        $channels = $notification->via($user);

        $this->assertContains('mail', $channels);
        $this->assertContains('database', $channels);
    }

    #[Test]
    public function via_does_not_add_mail_for_non_workflow_type(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'non_workflow_event',
            'model' => Ledger::class,
            'event' => 'non_workflow_event',
        ]);

        Permission::firstOrCreate(['name' => 'receive_workflow_action_email', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('receive_workflow_action_email');

        $notification = new GenericNotification($notificationType->id, $ledger);
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
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'approved',
            'model' => Ledger::class,
            'event' => 'approved',
        ]);

        $notification = new GenericNotification($notificationType->id, $ledger);

        $nonUser = new \stdClass;
        $result = $notification->toMail($nonUser);

        $this->assertNull($result);
    }

    #[Test]
    public function to_mail_returns_null_when_notification_type_not_found(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);

        // 存在しない ID を使う
        $notification = new GenericNotification(99999, $ledger);
        $user = User::factory()->create(['email' => 'test@example.com']);

        $result = $notification->toMail($user);

        $this->assertNull($result);
    }

    #[Test]
    public function to_mail_returns_null_when_user_has_no_email(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'approved',
            'model' => Ledger::class,
            'event' => 'approved',
        ]);

        $notification = new GenericNotification($notificationType->id, $ledger);
        $user = User::factory()->create(['email' => '']);

        $result = $notification->toMail($user);

        $this->assertNull($result);
    }

    // -------------------------------------------------------
    // toDatabase テスト
    // -------------------------------------------------------

    #[Test]
    public function to_database_returns_array_with_payload(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'approved',
            'model' => Ledger::class,
            'event' => 'approved',
        ]);
        $causer = User::factory()->create();
        $notifiable = User::factory()->create();

        $notification = new GenericNotification(
            $notificationType->id,
            $ledger,
            null,
            $causer,
            'approved',
            'コメント'
        );

        $result = $notification->toDatabase($notifiable);

        $this->assertArrayHasKey('type', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertEquals('approved', $result['payload']['notification_type_name']);
        $this->assertEquals($causer->id, $result['payload']['causer_id']);
        $this->assertEquals('コメント', $result['payload']['comments']);
    }

    #[Test]
    public function to_database_returns_empty_array_when_notification_type_not_found(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notifiable = User::factory()->create();

        $notification = new GenericNotification(99999, $ledger);
        $result = $notification->toDatabase($notifiable);

        $this->assertEmpty($result);
    }

    #[Test]
    public function to_database_includes_payload_overrides(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'inspection_requested',
            'model' => Ledger::class,
            'event' => 'inspection_requested',
        ]);
        $notifiable = User::factory()->create();

        $notification = new GenericNotification(
            $notificationType->id,
            $ledger,
            null,
            null,
            null,
            null,
            ['custom_key' => 'custom_value']
        );

        $result = $notification->toDatabase($notifiable);

        $this->assertEquals('custom_value', $result['payload']['custom_key']);
    }

    // -------------------------------------------------------
    // コンストラクタ causer 解決ロジックテスト
    // -------------------------------------------------------

    #[Test]
    public function constructor_sets_causer_from_explicit_user_parameter(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'approved',
            'model' => Ledger::class,
            'event' => 'approved',
        ]);
        $causer = User::factory()->create();

        $notification = new GenericNotification(
            $notificationType->id,
            $ledger,
            null,
            $causer
        );

        $notifiable = User::factory()->create();
        $result = $notification->toDatabase($notifiable);

        $this->assertEquals($causer->id, $result['payload']['causer_id']);
    }

    #[Test]
    public function constructor_sets_original_assignee(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);
        $ledger = Ledger::factory()->create(['ledger_define_id' => $ledgerDefine->id, 'content' => []]);
        $notificationType = NotificationType::create([
            'name' => 'task_claimed',
            'model' => Ledger::class,
            'event' => 'task_claimed',
        ]);
        $causer = User::factory()->create();
        $originalAssignee = User::factory()->create();

        $notification = new GenericNotification(
            $notificationType->id,
            $ledger,
            null,
            $causer,
            null,
            null,
            [],
            $originalAssignee
        );

        $this->assertEquals($originalAssignee->id, $notification->originalAssignee->id);
    }
}
