<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\OrganizationResource\Pages\CreateOrganization;
use App\Filament\Resources\OrganizationResource\Pages\EditOrganization;
use App\Filament\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(OrganizationResource::class)]
class OrganizationResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    private const PERMISSIONS = [
        'view_organizations', 'create_organizations', 'update_organizations',
        'delete_organizations', 'restore_organizations', 'force_delete_organizations',
        'manage_organizations',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'org-test.localhost']);
        tenancy()->initialize($this->tenant);

        foreach (self::PERMISSIONS as $perm) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);
        $adminRole->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole($adminRole);
        $this->actingAs($this->adminUser);
    }

    // ================================================================
    // ルーティング & レンダリング（公式: HTTP GET）
    // ================================================================

    #[Test]
    public function index_route_renders_successfully(): void
    {
        $this->get(OrganizationResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function create_route_renders_successfully(): void
    {
        $this->get(OrganizationResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function edit_route_renders_successfully(): void
    {
        $org = Organization::factory()->create(['name' => 'EditableOrg']);

        $this->get(OrganizationResource::getUrl('edit', ['record' => $org]))->assertSuccessful();
    }

    // ================================================================
    // テーブル（公式: assertCanSeeTableRecords / searchTable）
    // ================================================================

    #[Test]
    public function list_page_shows_existing_organizations(): void
    {
        $orgs = Organization::factory()->count(3)->create();

        Livewire::test(ListOrganizations::class)
            ->assertCanSeeTableRecords($orgs);
    }

    #[Test]
    public function list_page_can_search_by_name(): void
    {
        $target = Organization::factory()->create(['name' => 'UniqueSearchableOrg']);
        $other = Organization::factory()->create(['name' => 'ZZZOtherOrg']);

        Livewire::test(ListOrganizations::class)
            ->searchTable('UniqueSearchableOrg')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // ================================================================
    // 作成フォーム
    // ================================================================

    #[Test]
    public function create_page_validates_required_name(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    #[Test]
    public function create_page_can_create_organization(): void
    {
        Livewire::test(CreateOrganization::class)
            ->fillForm(['name' => 'NewOrganization'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('organizations', ['name' => 'NewOrganization']);
    }

    // ================================================================
    // 編集フォーム
    // ================================================================

    #[Test]
    public function edit_page_fills_existing_data(): void
    {
        $org = Organization::factory()->create(['name' => 'FilledOrg']);

        Livewire::test(EditOrganization::class, ['record' => $org->getRouteKey()])
            ->assertFormSet(['name' => 'FilledOrg']);
    }

    #[Test]
    public function edit_page_can_save_updated_name(): void
    {
        $org = Organization::factory()->create(['name' => 'OldOrgName']);

        Livewire::test(EditOrganization::class, ['record' => $org->getRouteKey()])
            ->fillForm(['name' => 'UpdatedOrgName'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'name' => 'UpdatedOrgName']);
    }

    // ================================================================
    // リソース静的メソッド
    // ================================================================

    #[Test]
    public function resource_has_correct_pages(): void
    {
        $pages = OrganizationResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
