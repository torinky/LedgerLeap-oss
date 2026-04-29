<?php

namespace Tests\Feature\Livewire\Notifications;

use App\Livewire\Notifications\Icon;
use App\Models\AdminAnnouncement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(Icon::class)]
class IconTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $tenancy = true;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        tenancy()->initialize($this->getTenant());

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    #[Test]
    public function it_links_to_the_global_notifications_page(): void
    {
        Livewire::test(Icon::class)
            ->assertSeeHtml('href="'.route('notifications.index').'"');
    }

    #[Test]
    public function it_shows_combined_notification_count_badge(): void
    {
        AdminAnnouncement::create([
            'title' => '運用通知',
            'body' => '確認が必要なお知らせです。',
            'level' => 'info',
            'status' => 'published',
            'priority' => 10,
            'scope' => ['current_tenant'],
        ]);

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\GenericNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'data' => json_encode([
                'payload' => [
                    'event' => 'created',
                    'causer_name' => 'テスト太郎',
                    'subject_type' => 'App\\Models\\Ledger',
                    'subject_id' => 1,
                ],
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(Icon::class)
            ->assertSet('unreadCount', 1)
            ->assertSet('adminAnnouncementCount', 1)
            ->assertSet('notificationCount', 2)
            ->assertSeeHtml('data-notification-count="2"')
            ->assertSeeHtml('btn-info');
    }
}
