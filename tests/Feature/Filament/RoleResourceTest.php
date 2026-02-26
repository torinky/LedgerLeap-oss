<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Filament\Resources\RoleResource\Pages\ListRoles;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RoleResource::class)]
class RoleResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    private const PERMISSIONS = [
        'view_roles', 'create_roles', 'update_roles',
        'delete_roles', 'manage_roles',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'role-test.localhost']);
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
        $this->get(RoleResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function create_route_renders_successfully(): void
    {
        $this->get(RoleResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function edit_route_renders_successfully(): void
    {
        $role = Role::firstOrCreate(['name' => 'EditableRole', 'guard_name' => 'web']);

        $this->get(RoleResource::getUrl('edit', ['record' => $role]))->assertSuccessful();
    }

    // ================================================================
    // テーブル（公式: assertCanSeeTableRecords / searchTable）
    // ================================================================

    #[Test]
    public function list_page_shows_existing_roles(): void
    {
        $role = Role::firstOrCreate(['name' => 'TestViewRole', 'guard_name' => 'web']);

        Livewire::test(ListRoles::class)
            ->assertCanSeeTableRecords(Role::where('name', 'TestViewRole')->get());
    }

    #[Test]
    public function list_page_can_search_by_name(): void
    {
        Role::firstOrCreate(['name' => 'SearchableRole', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'ZZZAnotherRole', 'guard_name' => 'web']);

        Livewire::test(ListRoles::class)
            ->searchTable('SearchableRole')
            ->assertCanSeeTableRecords(Role::where('name', 'SearchableRole')->get())
            ->assertCanNotSeeTableRecords(Role::where('name', 'ZZZAnotherRole')->get());
    }

    // ================================================================
    // 作成フォーム
    // ================================================================

    #[Test]
    public function create_page_validates_required_name(): void
    {
        Livewire::test(CreateRole::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    #[Test]
    public function create_page_can_create_role(): void
    {
        Livewire::test(CreateRole::class)
            ->fillForm([
                'name' => 'NewTestRole',
                'guard_name' => 'web',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('roles', ['name' => 'NewTestRole', 'guard_name' => 'web']);
    }

    // ================================================================
    // 編集フォーム
    // ================================================================

    #[Test]
    public function edit_page_fills_existing_data(): void
    {
        $role = Role::firstOrCreate(['name' => 'FilledRole', 'guard_name' => 'web']);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertFormSet(['name' => 'FilledRole']);
    }

    // ================================================================
    // リソース静的メソッド
    // ================================================================

    #[Test]
    public function resource_has_correct_pages(): void
    {
        $pages = RoleResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
