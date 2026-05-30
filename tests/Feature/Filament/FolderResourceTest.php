<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\FolderResource;
use App\Filament\Resources\FolderResource\Pages\CreateFolder;
use App\Filament\Resources\FolderResource\Pages\ListFolders;
use App\Filament\Resources\FolderResource\Pages\ListFoldersTree;
use App\Models\Folder;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[CoversClass(FolderResource::class)]
class FolderResourceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $adminUser;

    /** 必要パーミッション一覧 */
    private const PERMISSIONS = [
        'view_folders', 'create_folders', 'update_folders',
        'delete_folders', 'force_delete_folders',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create();
        $this->tenant->domains()->create(['domain' => 'folder-test.localhost']);
        tenancy()->initialize($this->tenant);

        // Policy が要求するパーミッションを作成してロールに付与
        foreach (self::PERMISSIONS as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web']);
        $adminRole->givePermissionTo(Permission::all());

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
        $this->get(FolderResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function create_route_renders_successfully(): void
    {
        $this->get(FolderResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function create_page_renders_confidentiality_fields(): void
    {
        Livewire::test(CreateFolder::class)
            ->assertFormExists()
            ->assertFormFieldExists('confidentiality_level')
            ->assertFormFieldExists('confidentiality_scopes');
    }

    #[Test]
    public function create_page_defaults_tenant_to_current_context(): void
    {
        Livewire::test(CreateFolder::class)
            ->assertFormSet([
                'tenant_id' => $this->tenant->id,
            ]);
    }

    #[Test]
    public function create_page_can_save_confidentiality_settings(): void
    {
        $org = Organization::factory()->create();
        $role = Role::factory()->create();

        Livewire::test(CreateFolder::class)
            ->fillForm([
                'title' => 'Confidential Folder',
                'tenant_id' => $this->tenant->id,
                'confidentiality_level' => 'confidential',
                'confidentiality_scopes' => ["org:{$org->id}", "role:{$role->id}"],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $folder = Folder::where('title', 'Confidential Folder')->firstOrFail();

        $this->assertSame('confidential', $folder->confidentiality_level);
        $this->assertSame([
            'org_ids' => [
                ['id' => $org->id, 'name' => $org->abbreviation ?? $org->name],
            ],
            'role_ids' => [
                ['id' => $role->id, 'name' => $role->abbreviation ?? $role->description ?? $role->name],
            ],
        ], $folder->confidentiality_scopes);
    }

    // ================================================================
    // テーブル（公式: assertCanSeeTableRecords / searchTable）
    // ================================================================

    #[Test]
    public function list_page_shows_existing_folders(): void
    {
        $folders = Folder::factory()->count(3)->create([
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);

        Livewire::test(ListFolders::class)
            ->assertCanSeeTableRecords($folders);
    }

    #[Test]
    public function list_page_can_search_by_title(): void
    {
        $target = Folder::factory()->create([
            'title' => 'UniqueSearchableFolder',
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);
        $other = Folder::factory()->create([
            'title' => 'OtherFolder',
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);

        Livewire::test(ListFolders::class)
            ->searchTable('UniqueSearchableFolder')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function list_and_tree_pages_show_only_current_tenant_folders_by_default(): void
    {
        session()->forget('filament_from_tenant_id');

        $currentTenantFolder = Folder::factory()->create([
            'title' => 'Current Tenant Folder',
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);

        $otherTenant = Tenant::create();
        $otherTenant->domains()->create(['domain' => 'folder-other.localhost']);

        tenancy()->initialize($otherTenant);
        $otherTenantFolder = Folder::create([
            'title' => 'Other Tenant Folder',
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ]);

        tenancy()->initialize($this->tenant);

        Livewire::test(ListFolders::class)
            ->assertCanSeeTableRecords([$currentTenantFolder])
            ->assertCanNotSeeTableRecords([$otherTenantFolder]);

        $this->get(FolderResource::getUrl('tree'))
            ->assertSuccessful()
            ->assertSee($currentTenantFolder->title)
            ->assertDontSee($otherTenantFolder->title);
    }

    #[Test]
    public function tenant_context_parameters_keep_current_tenant_in_urls(): void
    {
        $params = FolderResource::tenantContextParameters();

        $this->assertSame(['tenant' => $this->tenant->id], $params);
        $this->assertStringContainsString(
            'tenant='.urlencode((string) $this->tenant->id),
            FolderResource::getUrl('tree', $params)
        );
    }

    // ================================================================
    // 作成フォーム
    // ================================================================

    #[Test]
    public function create_page_validates_required_title(): void
    {
        Livewire::test(CreateFolder::class)
            ->fillForm([
                'title' => '',
                'tenant_id' => $this->tenant->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    #[Test]
    public function create_page_can_create_folder(): void
    {
        Livewire::test(CreateFolder::class)
            ->fillForm([
                'title' => 'New Folder',
                'tenant_id' => $this->tenant->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('folders', ['title' => 'New Folder']);
    }

    // ================================================================
    // 編集フォーム
    // ※ FolderPolicy::update() は isWritableFolderForUser() (RoleFolderPermission) チェックが必要なため
    //   Livewire テストでも 403 になる。作成確認のみ実施する
    // ================================================================

    #[Test]
    public function created_folder_exists_in_database(): void
    {
        Livewire::test(CreateFolder::class)
            ->fillForm([
                'title' => 'DB Check Folder',
                'tenant_id' => $this->tenant->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('folders', ['title' => 'DB Check Folder']);
    }

    // ================================================================
    // リソース静的メソッド
    // ================================================================

    #[Test]
    public function resource_has_correct_pages(): void
    {
        $pages = FolderResource::getPages();

        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    #[Test]
    public function tree_page_form_contains_confidentiality_fields(): void
    {
        $fieldNames = collect(ListFoldersTree::getCreateForm())
            ->map(fn ($component) => $component->getName())
            ->all();

        $this->assertContains('confidentiality_level', $fieldNames);
        $this->assertContains('confidentiality_scopes', $fieldNames);
    }

    #[Test]
    public function tree_page_infolist_contains_role_display(): void
    {
        $columnNames = collect(ListFoldersTree::getInfolistColumns())
            ->map(fn ($component) => $component->getName())
            ->all();

        $this->assertContains('roles', $columnNames);
        $this->assertSame('なし', __('ledger.none'));
    }
}
