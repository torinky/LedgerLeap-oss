<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\TagResource;
use App\Filament\Resources\TagResource\Pages\CreateTag;
use App\Filament\Resources\TagResource\Pages\EditTag;
use App\Filament\Resources\TagResource\Pages\ListTags;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(TagResource::class)]
class TagResourceTest extends TestCase
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

    /** テスト用 Tag を直接生成するヘルパー */
    private function makeTag(array $overrides = []): Tag
    {
        $folder = Folder::factory()->create();
        $define = LedgerDefine::factory()->for($folder)->create();

        return Tag::create(array_merge([
            'name' => 'TestTag-'.uniqid(),
            'folder_id' => $folder->id,
            'ledger_define_id' => $define->id,
            'creator_id' => $this->adminUser->id,
            'modifier_id' => $this->adminUser->id,
        ], $overrides));
    }

    // ================================================================
    // ルーティング & レンダリング
    // ================================================================

    #[Test]
    public function index_route_renders_successfully(): void
    {
        $this->get(TagResource::getUrl('index'))->assertSuccessful();
    }

    #[Test]
    public function create_route_renders_successfully(): void
    {
        $this->get(TagResource::getUrl('create'))->assertSuccessful();
    }

    #[Test]
    public function edit_route_renders_successfully(): void
    {
        $tag = $this->makeTag();

        $this->get(TagResource::getUrl('edit', ['record' => $tag]))->assertSuccessful();
    }

    // ================================================================
    // テーブル
    // ================================================================

    #[Test]
    public function list_page_shows_existing_tags(): void
    {
        $tag = $this->makeTag(['name' => 'VisibleTag']);

        Livewire::test(ListTags::class)
            ->assertCanSeeTableRecords(Tag::where('id', $tag->id)->get());
    }

    #[Test]
    public function list_page_can_search_tags_by_name(): void
    {
        $tag = $this->makeTag(['name' => 'SearchableTagXYZ']);
        $other = $this->makeTag(['name' => 'OtherTagXYZ']);

        Livewire::test(ListTags::class)
            ->searchTable('SearchableTagXYZ')
            ->assertCanSeeTableRecords(Tag::where('id', $tag->id)->get())
            ->assertCanNotSeeTableRecords(Tag::where('id', $other->id)->get());
    }

    // ================================================================
    // フォーム
    // ================================================================

    #[Test]
    public function create_page_renders_form_fields(): void
    {
        Livewire::test(CreateTag::class)
            ->assertFormExists()
            ->assertFormFieldExists('name');
    }

    #[Test]
    public function create_tag_requires_name(): void
    {
        Livewire::test(CreateTag::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    #[Test]
    public function edit_page_fills_existing_data(): void
    {
        $tag = $this->makeTag(['name' => 'EditableTag']);

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->assertFormSet(['name' => $tag->name]);
    }

    #[Test]
    public function can_edit_tag_name(): void
    {
        $tag = $this->makeTag(['name' => 'OldName']);

        Livewire::test(EditTag::class, ['record' => $tag->getRouteKey()])
            ->fillForm(['name' => 'NewName'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tags', ['id' => $tag->id, 'name' => 'NewName']);
    }

    // ================================================================
    // 静的メソッド
    // ================================================================

    #[Test]
    public function resource_has_correct_pages(): void
    {
        $pages = TagResource::getPages();
        $this->assertArrayHasKey('index', $pages);
        $this->assertArrayHasKey('create', $pages);
        $this->assertArrayHasKey('edit', $pages);
    }

    #[Test]
    public function resource_can_view_any(): void
    {
        $this->assertTrue(TagResource::canViewAny());
    }

    #[Test]
    public function resource_can_create(): void
    {
        $this->assertTrue(TagResource::canCreate());
    }

    #[Test]
    public function resource_can_edit_and_delete_record(): void
    {
        $tag = $this->makeTag();
        $this->assertTrue(TagResource::canEdit($tag));
        $this->assertTrue(TagResource::canDelete($tag));
        $this->assertTrue(TagResource::canDeleteAny());
    }
}
