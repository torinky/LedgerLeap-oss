<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\AdminAnnouncementBannerSettings;
use App\Filament\Widgets\DashboardLinksWidget;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AdminAnnouncementBannerSettings::class)]
class AdminAnnouncementBannerSettingsTest extends TestCase
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
    public function admin_announcement_banner_settings_page_renders_successfully(): void
    {
        $this->get(AdminAnnouncementBannerSettings::getUrl())->assertSuccessful();
    }

    #[Test]
    public function admin_announcement_banner_settings_mounts_default_draft(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->assertSet('data.status', 'draft')
            ->assertFormFieldExists('status')
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('body')
            ->assertFormFieldExists('level')
            ->assertFormFieldExists('sticky')
            ->assertFormFieldExists('scope')
            ->assertFormFieldExists('starts_at')
            ->assertFormFieldExists('ends_at')
            ->assertFormFieldExists('cta_label')
            ->assertFormFieldExists('cta_url')
            ->assertSeeHtml('data-admin-announcement-banner')
            ->assertSeeText(__('ledger.admin_announcement_banner_default_title'))
            ->assertSeeText(__('ledger.admin_announcement_banner_default_body'));
    }

    #[Test]
    public function admin_announcement_banner_settings_preview_updates_with_input(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.title', '運用通知')
            ->set('data.body', 'メンテナンス予定です。')
            ->set('data.level', 'critical')
            ->set('data.scope', 'all_tenants')
            ->set('data.sticky', true)
            ->set('data.cta_label', '詳細を見る')
            ->set('data.cta_url', 'https://example.com')
            ->assertSeeText('運用通知')
            ->assertSeeText('メンテナンス予定です。')
            ->assertSeeHtml('alert-error')
            ->assertSeeText(__('ledger.admin_announcement_banner_scope_all_tenants'))
            ->assertSeeText(__('ledger.admin_announcement_banner_sticky_on'))
            ->assertSeeText('詳細を見る')
            ->assertSeeHtml('href="https://example.com"');
    }

    #[Test]
    public function admin_announcement_banner_settings_critical_level_forces_sticky_on(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.sticky', false)
            ->set('data.level', 'critical')
            ->assertSet('data.sticky', true);
    }

    #[Test]
    public function admin_announcement_banner_settings_critical_preview_hides_close_button(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.level', 'critical')
            ->assertSeeHtml('alert-error')
            ->assertSeeText(__('ledger.admin_announcement_banner_sticky_on'))
            ->assertDontSeeHtml('aria-label="'.__('ledger.close').'"');
    }

    #[Test]
    public function admin_announcement_banner_settings_preview_reset_increments_nonce(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->assertSet('previewResetNonce', 0)
            ->call('resetPreviewBanner')
            ->assertSet('previewResetNonce', 1);
    }

    #[Test]
    public function admin_announcement_banner_settings_save_draft_switches_status_to_draft(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.status', 'archived')
            ->call('saveDraft')
            ->assertSet('data.status', 'draft');
    }

    #[Test]
    public function admin_announcement_banner_settings_publish_switches_status_to_published(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.status', 'draft')
            ->set('data.starts_at', '2026-04-28 10:00:00')
            ->set('data.ends_at', '2026-04-28 11:00:00')
            ->call('publishAnnouncement')
            ->assertSet('data.status', 'published');
    }

    #[Test]
    public function admin_announcement_banner_settings_publish_rejects_invalid_period(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.status', 'draft')
            ->set('data.starts_at', '2026-04-28 11:00:00')
            ->set('data.ends_at', '2026-04-28 10:00:00')
            ->call('publishAnnouncement')
            ->assertHasErrors(['ends_at' => 'after_or_equal']);
    }

    #[Test]
    public function admin_announcement_banner_settings_archive_switches_status_to_archived(): void
    {
        Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.status', 'published')
            ->call('archiveAnnouncement')
            ->assertSet('data.status', 'archived');
    }

    #[Test]
    public function admin_announcement_banner_settings_reset_restores_default_values(): void
    {
        $component = Livewire::test(AdminAnnouncementBannerSettings::class)
            ->set('data.title', 'カスタム見出し')
            ->set('data.level', 'critical')
            ->call('resetDraft');

        $component
            ->assertSet('data.title', __('ledger.admin_announcement_banner_default_title'))
            ->assertSet('data.level', 'warning');
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
        $this->assertContains(__('ledger.admin_announcement_banner_title'), array_column($contentsGroup['links'], 'title'));
    }
}
