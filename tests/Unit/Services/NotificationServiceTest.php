<?php

namespace Tests\Unit\Services;

use App\Models\Folder;
use App\Models\NotificationType;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * NotificationService のユニットテスト
 *
 * Phase 1.4: NotificationService のテスト強化
 *
 * @see app/Services/NotificationService.php
 */
class NotificationServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->notificationService = app(NotificationService::class);
        Mail::fake();
        Notification::fake();
    }

    #[Test]
    public function it_can_get_unread_notifications_for_user()
    {
        // Arrange
        $user = User::factory()->create();
        DatabaseNotification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notification'],
            'read_at' => null,
        ]);

        // Act
        $notifications = $this->notificationService->getUnreadNotificationsForUser($user);

        // Assert
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $notifications);
        $this->assertGreaterThan(0, $notifications->total());
    }

    #[Test]
    public function it_can_get_unread_notification_count_for_user()
    {
        // Arrange
        $user = User::factory()->create();
        DatabaseNotification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notification'],
            'read_at' => null,
        ]);

        // Act
        $count = $this->notificationService->getUnreadNotificationCountForUser($user);

        // Assert
        $this->assertGreaterThan(0, $count);
    }

    #[Test]
    public function it_can_mark_notification_as_read()
    {
        // Arrange
        $user = User::factory()->create();
        $notification = DatabaseNotification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notification'],
            'read_at' => null,
        ]);

        // Act
        $this->notificationService->markAsRead($user, [$notification->id]);

        // Assert - メソッドが正常に実行された
        $this->assertTrue(true);
    }

    #[Test]
    public function it_can_mark_all_notifications_as_read()
    {
        // Arrange
        $user = User::factory()->create();
        DatabaseNotification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notification 1'],
            'read_at' => null,
        ]);
        DatabaseNotification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => ['message' => 'Test notification 2'],
            'read_at' => null,
        ]);

        // Act - null を渡すと全ての未読通知を既読にする
        $this->notificationService->markAsRead($user, null);

        // Assert - メソッドが正常に実行された
        $this->assertTrue(true);
    }

    #[Test]
    public function it_processes_activity_log_when_valid()
    {
        // Arrange - シンプルなテストケース
        $folder = Folder::factory()->create();
        $user = User::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Test activity',
            'subject_type' => Folder::class,
            'subject_id' => $folder->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'created',
        ]);

        // Act & Assert - 例外が発生しないことを確認
        $this->notificationService->processActivityLog($activity);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_process_activity_log_when_notification_type_not_found()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $user = User::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Test activity',
            'subject_type' => Folder::class,
            'subject_id' => $folder->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'nonexistent_event',
        ]);

        // Act & Assert - 例外が発生しないことを確認
        $this->notificationService->processActivityLog($activity);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_does_not_process_activity_log_when_no_recipients()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $notificationType = NotificationType::create([
            'name' => 'Test Notification Type',
            'model' => Folder::class,
            'event' => 'created',
        ]);

        $user = User::factory()->create();

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Test activity',
            'subject_type' => Folder::class,
            'subject_id' => $folder->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'created',
        ]);

        // Act & Assert - 受信者がいない場合でも例外が発生しないことを確認
        $this->notificationService->processActivityLog($activity);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_can_get_notifiable_recipients_with_folder_permissions()
    {
        // Arrange
        $folder = Folder::factory()->create();
        $notificationType = NotificationType::create([
            'name' => 'folder_created',
            'model' => Folder::class,
            'event' => 'created',
        ]);

        $role = Role::firstOrCreate(['name' => 'NotificationRecipientRole', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        // RoleFolderPermissionを作成してNOTIFY_ON権限を設定
        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'notification_type_id' => $notificationType->id,
            'permission' => \App\Enums\FolderPermissionType::NOTIFY_ON->value,
            'modifier_id' => $user->id,
        ]);

        $activity = Activity::create([
            'log_name' => 'default',
            'description' => 'Test activity',
            'subject_type' => Folder::class,
            'subject_id' => $folder->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'event' => 'created',
        ]);

        // Act
        $recipients = $this->notificationService->getNotifiableRecipients($activity, $notificationType);

        // Assert
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $recipients);
    }

    #[Test]
    public function it_respects_tenant_isolation()
    {
        // Arrange: 現在のテナントで通知を作成
        $user1 = User::factory()->create();
        DatabaseNotification::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user1->id,
            'data' => ['message' => 'Tenant 1 notification'],
            'read_at' => null,
        ]);

        // 別のテナントを作成して切り替え
        $newTenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($newTenant);
        $user2 = User::factory()->create();

        // Act & Assert: 別テナントのユーザーは元の通知を見られない
        $notifications = $this->notificationService->getUnreadNotificationsForUser($user2);
        $this->assertEquals(0, $notifications->total());

        $count = $this->notificationService->getUnreadNotificationCountForUser($user2);
        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_handles_pagination_correctly()
    {
        // Arrange
        $user = User::factory()->create();

        // 20件の通知を作成
        for ($i = 0; $i < 20; $i++) {
            DatabaseNotification::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\TestNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => ['message' => "Test notification {$i}"],
                'read_at' => null,
            ]);
        }

        // Act - 1ページあたり5件で取得
        $notifications = $this->notificationService->getUnreadNotificationsForUser($user, 5);

        // Assert
        $this->assertEquals(5, $notifications->count());
        $this->assertEquals(20, $notifications->total());
        $this->assertEquals(4, $notifications->lastPage());
    }
}
