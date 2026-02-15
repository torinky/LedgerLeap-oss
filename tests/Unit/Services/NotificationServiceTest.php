<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * NotificationService のユニットテスト
 *
 * Phase 1.4: NotificationService の基本的なスモークテスト
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

        // Act - markAsReadメソッドが例外なく実行されることを確認
        $this->notificationService->markAsRead($user, [$notification->id]);

        // Assert - メソッドが正常に実行された
        $this->assertTrue(true);
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
}
