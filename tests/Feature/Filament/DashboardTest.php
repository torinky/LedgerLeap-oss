<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardLinksWidget;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Dashboard::class)]
#[CoversClass(DashboardLinksWidget::class)]
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'dashboard-test.localhost']);
        tenancy()->initialize($this->tenant);

        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    // ================================================================
    // Dashboard ページ
    // ================================================================

    #[Test]
    public function dashboard_page_renders_successfully(): void
    {
        $this->get(Dashboard::getUrl())->assertSuccessful();
    }

    #[Test]
    public function dashboard_mounts_from_tenant_query_param(): void
    {
        Livewire::test(Dashboard::class, ['fromTenant' => null])
            ->assertSet('fromTenant', null);
    }

    #[Test]
    public function dashboard_get_title_returns_string(): void
    {
        $comp = Livewire::test(Dashboard::class);
        $title = $comp->instance()->getTitle();
        $this->assertNotEmpty($title);
    }

    #[Test]
    public function dashboard_get_widgets_includes_dashboard_links_widget(): void
    {
        $comp = Livewire::test(Dashboard::class);
        $widgets = $comp->instance()->getWidgets();
        $this->assertContains(DashboardLinksWidget::class, $widgets);
    }

    // ================================================================
    // DashboardLinksWidget
    // ================================================================

    #[Test]
    public function dashboard_links_widget_renders_successfully(): void
    {
        Livewire::test(DashboardLinksWidget::class)
            ->assertStatus(200);
    }

    #[Test]
    public function dashboard_links_widget_can_view_returns_true(): void
    {
        $this->assertTrue(DashboardLinksWidget::canView());
    }

    #[Test]
    public function dashboard_links_widget_mounts_without_tenant_session(): void
    {
        session()->forget('filament_from_tenant_id');

        Livewire::test(DashboardLinksWidget::class)
            ->assertSet('from_tenant', null);
    }

    #[Test]
    public function dashboard_links_widget_mounts_with_tenant_session(): void
    {
        session()->put('filament_from_tenant_id', $this->tenant->id);

        // テナントセッションがある場合のマウント（通知送信は副作用なのでエラーにならないことを確認）
        Livewire::test(DashboardLinksWidget::class)
            ->assertSet('from_tenant', $this->tenant->id);
    }

    #[Test]
    public function dashboard_links_widget_get_view_data_contains_groups(): void
    {
        $comp = Livewire::test(DashboardLinksWidget::class);
        $instance = $comp->instance();

        $ref = new \ReflectionMethod($instance, 'getViewData');
        $viewData = $ref->invoke($instance);

        $this->assertArrayHasKey('groups', $viewData);
        $this->assertNotEmpty($viewData['groups']);
    }

    #[Test]
    public function dashboard_links_widget_get_groups_returns_central_groups_without_tenant(): void
    {
        session()->forget('filament_from_tenant_id');
        $comp = Livewire::test(DashboardLinksWidget::class);
        $instance = $comp->instance();

        // getGroups() は protected なのでリフレクションで呼び出す
        $ref = new \ReflectionMethod($instance, 'getGroups');
        $groups = $ref->invoke($instance);

        $this->assertIsArray($groups);
        $this->assertNotEmpty($groups);
    }

    #[Test]
    public function dashboard_links_widget_get_groups_includes_tenant_group_when_from_tenant_set(): void
    {
        session()->put('filament_from_tenant_id', $this->tenant->id);
        session()->forget('tenant_context_notified_'.$this->tenant->id);

        $comp = Livewire::test(DashboardLinksWidget::class);
        $instance = $comp->instance();

        $ref = new \ReflectionMethod($instance, 'getGroups');
        $groups = $ref->invoke($instance);

        // テナント固有グループが先頭に追加される
        $this->assertCount(4, $groups); // tenant group + 3 central groups
    }
}
