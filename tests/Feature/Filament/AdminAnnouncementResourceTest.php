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
use Carbon\CarbonImmutable;
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
        CarbonImmutable::setTestNow('2026-04-28 12:00:00');

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'announcement-banner-test.localhost']);
        tenancy()->initialize($this->tenant);

        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function resourceListPageRendersSuccessfully(): void
    {
        $this->get(AdminAnnouncementResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function listPageShowsExistingAnnouncements(): void
    {
        $published = AdminAnnouncement::query()->forceCreate([
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

        $draft = AdminAnnouncement::query()->forceCreate([
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
            ->assertSeeHtmlInOrder([
                __('ledger.admin_announcement_banner_status_label'),
                __('ledger.admin_announcement_banner_field_title'),
                __('ledger.admin_announcement_banner_level_label'),
                __('ledger.admin_announcement_banner_publish_scope'),
                __('ledger.admin_announcement_banner_starts_at'),
                __('ledger.admin_announcement_banner_ends_at'),
                __('ledger.updated_at'),
                __('ledger.admin_announcement_banner_creator_label'),
                __('ledger.admin_announcement_banner_modifier_label'),
            ])
            ->assertCanSeeTableRecords([$published, $draft]);
    }

    #[Test]
    public function listPageShowsDisplayStatusLabels(): void
    {
        AdminAnnouncement::query()->forceCreate([
            'title' => '公開中のお知らせ',
            'body' => '公開中の本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 10,
            'starts_at' => '2026-04-28 10:00:00',
            'ends_at' => '2026-04-28 13:00:00',
        ]);

        AdminAnnouncement::query()->forceCreate([
            'title' => '公開予定のお知らせ',
            'body' => '公開予定の本文です。',
            'level' => 'info',
            'status' => 'published',
            'scope' => ['current_tenant'],
            'sticky' => false,
            'priority' => 20,
            'starts_at' => '2026-04-28 13:00:00',
            'ends_at' => '2026-04-28 14:00:00',
        ]);

        AdminAnnouncement::query()->forceCreate([
            'title' => '公開終了のお知らせ',
            'body' => '公開終了の本文です。',
            'level' => 'critical',
            'status' => 'published',
            'scope' => ['current_tenant'],
            'sticky' => true,
            'priority' => 30,
            'starts_at' => '2026-04-28 10:00:00',
            'ends_at' => '2026-04-28 11:00:00',
        ]);

        Livewire::test(ListAdminAnnouncements::class)
            ->assertSeeText(__('ledger.admin_announcement_banner_status_published'))
            ->assertSeeText(__('ledger.admin_announcement_banner_status_scheduled'))
            ->assertSeeText(__('ledger.admin_announcement_banner_status_ended'));
    }

    #[Test]
    public function listPageShowsStatusIndicatorsAndCanReplicateAnnouncements(): void
    {
        $published = AdminAnnouncement::query()->forceCreate([
            'title' => '複製元のお知らせ',
            'body' => '複製したい本文です。',
            'level' => 'warning',
            'status' => 'published',
            'scope' => ['all_tenants'],
            'sticky' => true,
            'priority' => 15,
            'starts_at' => '2026-04-28 10:00:00',
            'ends_at' => '2026-04-29 10:00:00',
            'links' => [
                ['label' => '詳細', 'url' => 'https://example.com/source'],
            ],
        ]);

        Livewire::test(ListAdminAnnouncements::class)
            ->assertSeeText(__('ledger.admin_announcement_banner_status_published'))
            ->assertSeeHtml(__('ledger.admin_announcement_banner_status_published'));

        Livewire::test(ListAdminAnnouncements::class)
            ->callTableAction('replicate', $published);

        $this->assertSame(2, AdminAnnouncement::query()->count());
        $this->assertTrue(AdminAnnouncement::query()->where([
            'title' => '複製元のお知らせ',
            'body' => '複製したい本文です。',
            'level' => 'warning',
            'status' => 'draft',
            'sticky' => true,
            'priority' => 15,
        ])->exists());
        $this->assertTrue(AdminAnnouncement::query()->where([
            'title' => '複製元のお知らせ',
            'body' => '複製したい本文です。',
            'level' => 'warning',
            'status' => 'published',
        ])->exists());
    }

    #[Test]
    public function createPageValidatesRequiredFields(): void
    {
        Livewire::test(CreateAdminAnnouncement::class)
            ->fillForm([
                'title' => '',
                'body' => '',
                'level' => '',
                'scope' => [],
                'starts_at' => null,
                'ends_at' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'body' => 'required',
                'level' => 'required',
                'scope' => 'required',
                'starts_at' => 'required',
                'ends_at' => 'required',
            ]);
    }

    #[Test]
    public function createPageValidatesEndsAtIsAfterStartsAt(): void
    {
        Livewire::test(CreateAdminAnnouncement::class)
            ->fillForm([
                'title' => '期間確認',
                'body' => '期間の整合性を確認します。',
                'level' => 'warning',
                'scope' => ['current_tenant'],
                'sticky' => false,
                'priority' => 3,
                'starts_at' => '2026-04-28 13:00:00',
                'ends_at' => '2026-04-28 12:00:00',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'ends_at' => 'after_or_equal',
            ]);
    }

    #[Test]
    public function createPageCanPersistDraftToList(): void
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

        $this->assertTrue(AdminAnnouncement::query()->where([
            'title' => '新しい下書き',
            'status' => 'draft',
            'priority' => 3,
        ])->exists());

        $this->get(AdminAnnouncementResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSeeText('新しい下書き');
    }

    #[Test]
    public function editPagePrefillsExistingValues(): void
    {
        $announcement = AdminAnnouncement::query()->forceCreate([
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
                'status' => 'scheduled',
                'scope' => ['all_tenants'],
                'sticky' => true,
                'priority' => 7,
                'cta_label' => '詳細を見る',
                'cta_url' => 'https://example.com/edit',
            ]);
    }

    #[Test]
    public function editPageRendersPreviewSection(): void
    {
        $announcement = AdminAnnouncement::query()->forceCreate([
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
    public function editPageCanSaveUpdates(): void
    {
        $announcement = AdminAnnouncement::query()->forceCreate([
            'title' => '元タイトル',
            'body' => '元本文',
            'level' => 'info',
            'status' => 'draft',
            'scope' => ['current_tenant'],
            'starts_at' => '2026-04-28 12:00:00',
            'ends_at' => '2026-04-28 13:00:00',
        ]);

        Livewire::test(EditAdminAnnouncement::class, ['record' => $announcement->getRouteKey()])
            ->fillForm([
                'title' => '更新後タイトル',
                'body' => '更新後本文',
                'level' => 'warning',
                'scope' => ['all_tenants'],
                'sticky' => true,
                'priority' => 5,
                'starts_at' => '2026-04-28 12:00:00',
                'ends_at' => '2026-04-28 13:00:00',
                'cta_label' => '詳細',
                'cta_url' => 'https://example.com/update',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(AdminAnnouncement::query()->where([
            'id' => $announcement->id,
            'title' => '更新後タイトル',
            'status' => 'draft',
            'priority' => 5,
        ])->exists());
    }

    #[Test]
    public function dashboardLinksWidgetIncludesAnnouncementBannerLink(): void
    {
        session()->put('filament_from_tenant_id', $this->tenant->id);

        $component = Livewire::test(DashboardLinksWidget::class);
        $instance = $component->instance();

        $ref = new \ReflectionMethod($instance, 'getGroups');
        $groups = $ref->invoke($instance);
        $contentsGroup = collect($groups)->firstWhere('title', __('ledger.settings.contents'));

        $this->assertNotNull($contentsGroup);
        $expectedTitle = __('ledger.admin_announcement_banner_title').' '.__('ledger.setting');
        $expectedUrl = AdminAnnouncementResource::getUrl('index').'?tenant='.$this->tenant->id;

        $this->assertContains($expectedTitle, array_column($contentsGroup['links'], 'title'));
        $this->assertContains($expectedUrl, array_column($contentsGroup['links'], 'url'));
    }
}
