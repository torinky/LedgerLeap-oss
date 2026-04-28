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
            ->assertFormFieldExists('title')
            ->assertFormFieldExists('body')
            ->assertFormFieldExists('level')
            ->assertFormFieldExists('sticky')
            ->assertFormFieldExists('scope')
            ->assertFormFieldExists('starts_at')
            ->assertFormFieldExists('ends_at')
            ->assertFormFieldExists('cta_label')
            ->assertFormFieldExists('cta_url')
                ->assertSeeHtml('data-admin-announcement-banner');
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
