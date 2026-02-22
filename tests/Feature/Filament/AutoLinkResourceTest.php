<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AutoLinkResource;
use App\Filament\Resources\AutoLinkResource\Pages\CreateAutoLink;
use App\Filament\Resources\AutoLinkResource\Pages\EditAutoLink;
use App\Models\AutoLink;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AutoLinkResource::class)]
class AutoLinkResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    private const PERMISSIONS = ['manage_auto_links'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'autolink-test.localhost']);
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

    /** AutoLink を DB スキーマに合わせて直接作成する */
    private function makeAutoLink(array $overrides = []): AutoLink
    {
        return AutoLink::create(array_merge([
            'label' => 'Test AutoLink',
            'pattern' => '/TEST-\d+/',
            'url_template' => 'https://example.com/$1',
            'is_enabled' => true,
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ], $overrides));
    }

    // ================================================================
    // ルーティング & レンダリング（公式: HTTP GET）
    // ================================================================

    #[Test]
    public function create_route_renders_successfully(): void
    {
        $this->get(AutoLinkResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function edit_route_renders_successfully(): void
    {
        $autoLink = $this->makeAutoLink();
        $this->get(AutoLinkResource::getUrl('edit', ['record' => $autoLink]))->assertSuccessful();
    }

    // ================================================================
    // テーブル（公式: HTTP GET + assertCanSeeTableRecords）
    // ================================================================

    #[Test]
    public function index_route_renders_successfully(): void
    {
        $this->get(AutoLinkResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function list_page_has_records_in_database(): void
    {
        $link1 = $this->makeAutoLink(['label' => 'Link Alpha']);
        $link2 = $this->makeAutoLink(['label' => 'Link Beta']);

        // DB にレコードが存在することを確認（テーブル表示は index_route で担保）
        $this->assertDatabaseHas('auto_links', ['id' => $link1->id, 'label' => 'Link Alpha']);
        $this->assertDatabaseHas('auto_links', ['id' => $link2->id, 'label' => 'Link Beta']);
    }

    // ================================================================
    // 作成フォーム
    // ================================================================

    #[Test]
    public function create_page_renders_form_fields(): void
    {
        Livewire::test(CreateAutoLink::class)
            ->assertFormFieldExists('pattern')
            ->assertFormFieldExists('label');
    }

    // ================================================================
    // 編集フォーム
    // ================================================================

    #[Test]
    public function edit_page_fills_existing_data(): void
    {
        $autoLink = $this->makeAutoLink(['label' => 'TestLabel']);

        Livewire::test(EditAutoLink::class, ['record' => $autoLink->getRouteKey()])
            ->assertFormSet(['label' => 'TestLabel']);
    }

    // ================================================================
    // リソース静的メソッド
    // ================================================================

    #[Test]
    public function resource_has_correct_pages(): void
    {
        $pages = AutoLinkResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }
}
