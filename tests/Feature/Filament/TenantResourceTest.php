<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TenantResource;
use App\Filament\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Resources\TenantResource\Pages\ListTenants;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(TenantResource::class)]
class TenantResourceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    // ================================================================
    // ルーティング & レンダリング
    // ================================================================

    #[Test]
    public function index_route_renders_successfully(): void
    {
        $this->get(TenantResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function create_route_renders_successfully(): void
    {
        $this->get(TenantResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function edit_route_renders_successfully(): void
    {
        $tenant = static::$sharedTenant;

        $this->get(TenantResource::getUrl('edit', ['record' => $tenant]))->assertSuccessful();
    }

    // ================================================================
    // テーブル
    // ================================================================

    #[Test]
    public function list_page_shows_existing_tenants(): void
    {
        Livewire::test(ListTenants::class)
            ->assertCanSeeTableRecords(
                Tenant::where('id', static::$sharedTenant->id)->get()
            );
    }

    // ================================================================
    // フォーム
    // ================================================================

    #[Test]
    public function edit_page_renders_form_fields(): void
    {
        $tenant = static::$sharedTenant;

        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
            ->assertFormExists()
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('description');
    }

    #[Test]
    public function can_edit_tenant_without_errors(): void
    {
        $tenant = static::$sharedTenant;
        $currentName = $tenant->name ?: 'TestTenant';

        Livewire::test(EditTenant::class, ['record' => $tenant->getRouteKey()])
            ->fillForm(['name' => $currentName, 'description' => 'Updated desc'])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    // ================================================================
    // 静的メソッド
    // ================================================================

    #[Test]
    public function resource_has_correct_pages(): void
    {
        $pages = TenantResource::getPages();
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    #[Test]
    public function resource_returns_empty_relations(): void
    {
        $this->assertEmpty(TenantResource::getRelations());
    }
}
