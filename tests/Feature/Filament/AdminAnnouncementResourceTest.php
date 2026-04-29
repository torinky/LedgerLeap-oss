<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AdminAnnouncementResource;
use App\Filament\Resources\AdminAnnouncementResource\Pages\CreateAdminAnnouncement;
use App\Filament\Resources\AdminAnnouncementResource\Pages\EditAdminAnnouncement;
use App\Filament\Resources\AdminAnnouncementResource\Pages\ListAdminAnnouncements;
use App\Filament\Widgets\DashboardLinksWidget;
use App\Models\AdminAnnouncement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AdminAnnouncementResource::class)]
class AdminAnnouncementResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'announcement-banner-test.localhost']);
        tenancy()->initialize($this->tenant);

        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    #[Test]
    public function resource_list_page_renders_successfully(): void
    {
        $this->get(AdminAnnouncementResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function list_page_shows_existing_announcements(): void
    {
        $published = AdminAnnouncement::create([
            'title' => '公開中のお知らせ',
            'body' => '公開中の本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 10,
            'starts_at' => '2026-04-28 10:00:00',
            'links' => [
                ['label' => '詳細', 'url' => 'https://example.com/published'],
            ],
        ]);

        $draft = AdminAnnouncement::create([
            'title' => '下書きのお知らせ',
            'body' => '下書きの本文です。',
            'level' => 'info',
            'status' => 'draft',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 0,
            'starts_at' => '2026-04-28 11:00:00',
        ]);

        Livewire::test(ListAdminAnnouncements::class)
            ->assertCanSeeTableRecords([$published, $draft]);
    }

    #[Test]
    public function create_page_can_persist_draft_to_list(): void
    {
        Livewire::test(CreateAdminAnnouncement::class)
            ->fillForm([
                'title' => '新しい下書き',
                'body' => '保存される本文です。',
                'level' => 'warning',
                'scope' => ['current_tenant', 'all_tenants'],
                'sticky' => false,
                'priority' => 3,
                'starts_at' => '2026-04-28 12:00:00',
                'ends_at' => '2026-04-28 13:00:00',
                'cta_label' => '詳細を見る',
                'cta_url' => 'https://example.com/draft',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admin_announcements', [
            'title' => '新しい下書き',
            'status' => 'draft',
            'priority' => 3,
        ]);

        $this->get(AdminAnnouncementResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSeeText('新しい下書き');
    }

    #[Test]
    public function edit_page_prefills_existing_values(): void
    {
        $announcement = AdminAnnouncement::create([
            'title' => '編集対象',
            'body' => '編集対象の本文です。',
            'level' => 'critical',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 7,
            'starts_at' => '2026-04-28 14:00:00',
            'ends_at' => '2026-04-28 15:00:00',
            'links' => [
                ['label' => '詳細を見る', 'url' => 'https://example.com/edit'],
            ],
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->assertFormSet([
                'title' => '編集対象',
                'body' => '編集対象の本文です。',
                'level' => 'critical',
                'scope' => ['all_tenants'],
                'sticky' => true,
                'priority' => 7,
                'cta_label' => '詳細を見る',
                'cta_url' => 'https://example.com/edit',
            ]);
    }

    #[Test]
    public function edit_page_renders_preview_section(): void
    {
        $announcement = AdminAnnouncement::create([
            'title' => 'プレビュー確認',
            'body' => 'プレビューの本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 2,
            'starts_at' => '2026-04-28 16:00:00',
            'ends_at' => '2026-04-28 17:00:00',
            'links' => [
                ['label' => '詳細', 'url' => 'https://example.com/preview'],
            ],
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->assertSeeText(__('ledger.admin_announcement_banner_preview_title'))
            ->assertSeeText(__('ledger.admin_announcement_banner_preview_hint'))
            ->assertSeeText(__('ledger.admin_announcement_banner_display_state_title'))
            ->assertSeeText(__('ledger.admin_announcement_banner_preview_reset'))
            ->assertSeeHtml('data-admin-announcement-banner');
    }

    #[Test]
    public function edit_page_can_save_updates(): void
    {
        $announcement = AdminAnnouncement::create([
            'title' => '元タイトル',
            'body' => '元本文',
            'level' => 'info',
            'status' => 'draft',
            'scope' => ['current_tenant'],
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm([
                'title' => '更新後タイトル',
                'body' => '更新後本文',
                'level' => 'warning',
                'scope' => ['all_tenants'],
                'sticky' => true,
                'priority' => 5,
                'cta_label' => '詳細',
                'cta_url' => 'https://example.com/update',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admin_announcements', [
            'id' => $announcement->id,
            'title' => '更新後タイトル',
            'status' => 'draft',
            'priority' => 5,
        ]);
    }

    #[Test]
    public function dashboard_links_widget_includes_announcement_banner_link(): void
    {
        session()->put('filament_from_tenant_id', $this->tenant->id);

        $component = Livewire::test(DashboardLinksWidget::class);
        $instance = $component->instance();

        $ref = new \ReflectionMethod($instance, 'getGroups');
        $groups = $ref->invoke($instance);
        $contentsGroup = collect($groups)->firstWhere('title', __('ledger.settings.contents'));

        $this->assertNotNull($contentsGroup);
        $this->assertContains(__('ledger.admin_announcement_banner_title').' '.__('ledger.setting'), array_column($contentsGroup['links'], 'title'));
        $this->assertContains(AdminAnnouncementResource::getUrl('index').'?tenant='.$this->tenant->id, array_column($contentsGroup['links'], 'url'));
    }
}