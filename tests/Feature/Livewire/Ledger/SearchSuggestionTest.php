<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\IndexManager;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SearchSuggestionTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        Livewire::withoutLazyLoading();

        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create([
            'email' => 'test.'.Str::random(10).'@example.com',
        ]);
        $this->actingAs($this->user);

        Permission::firstOrCreate(['name' => 'view_ledger_defines', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_auto_links', 'guard_name' => 'web']);
        $this->user->givePermissionTo(['view_ledger_defines', 'ledgerView', 'view_auto_links']);

        $rootFolder = Folder::factory()->create(['parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => $rootFolder->id]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('getManageableFolderIds')->andReturn([]);
        $mockRepository->shouldReceive('getWritableFolderIds')->andReturn([]);
        $mockRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
        $mockRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);
        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => 'テキストカラム', 'type' => 'text', 'order' => 1, 'display_level' => 1],
            ],
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => 'サンプルキーワード'],
            'content_attached' => [0 => []],
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function search_execution_creates_activity_log_record(): void
    {
        Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->set('search', 'サンプルキーワード');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'searched',
            'causer_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function index_manager_passes_recent_searches_to_view(): void
    {
        CustomActivity::create([
            'log_name' => 'search',
            'description' => '検索実行: サンプル',
            'event' => 'searched',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'properties' => [
                'conditions' => ['q' => 'サンプル', 'sort' => 'composite_score', 'dir' => 'desc'],
                'result_count' => 1,
            ],
        ]);

        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->assertViewHas('recentSearches', function ($recentSearches) {
            return count($recentSearches) === 1 && $recentSearches[0]['conditions']['q'] === 'サンプル';
        });
    }

    #[Test]
    public function apply_search_restores_conditions(): void
    {
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->call('applySearch', [
            'q' => '復元キーワード',
            'sort' => 'created_at',
            'dir' => 'asc',
            'status' => '',
            'filter' => [],
            'l' => [$this->ledgerDefine->id],
            'f' => [$this->folder->id],
            'cf' => $this->folder->id,
            'dl' => 2,
            'pp' => 50,
            'sem' => false,
            'syn' => true,
            'tt' => true,
        ]);

        $component->assertSet('search', '復元キーワード')
            ->assertSet('orderBy', 'created_at')
            ->assertSet('orderAsc', true)
            ->assertSet('displayLevel', 2)
            ->assertSet('perPage', 50);
    }

    #[Test]
    public function recent_searches_exclude_unauthorized_folders(): void
    {
        CustomActivity::create([
            'log_name' => 'search',
            'description' => '検索実行: 権限外',
            'event' => 'searched',
            'causer_type' => User::class,
            'causer_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'properties' => [
                'conditions' => ['q' => '権限外', 'f' => [9999], 'cf' => 9999],
                'result_count' => 0,
            ],
        ]);

        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->assertViewHas('recentSearches', function ($recentSearches) {
            return count($recentSearches) === 0;
        });
    }

    // ---------------------------------------------------------------
    // Phase A regression: server methods (called from Alpine) must
    // not accidentally trigger full ledger search or record history.
    // ---------------------------------------------------------------

    #[Test]
    public function update_suggestions_does_not_change_search_property(): void
    {
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->call('updateSuggestions', '部品');

        // updateSuggestions MUST NOT set $this->search
        $component->assertSet('search', '');
    }

    #[Test]
    public function update_suggestions_does_not_record_history(): void
    {
        Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->call('updateSuggestions', '部品');

        // updateSuggestions MUST NOT create activity log
        $this->assertDatabaseMissing('activity_log', [
            'event' => 'searched',
        ]);
    }

    #[Test]
    public function execute_search_sets_search_and_records_history(): void
    {
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->call('executeSearch', 'キーワード');

        $component->assertSet('search', 'キーワード');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'searched',
            'causer_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function execute_search_increments_records_table_mount_key(): void
    {
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);
        $before = $component->get('recordsTableMountKey');

        $component->call('executeSearch', 'キーワード');

        $after = $component->get('recordsTableMountKey');
        $this->assertGreaterThan($before, $after);
    }

    #[Test]
    public function clear_search_resets_search_to_empty(): void
    {
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->call('executeSearch', 'キーワード');
        $component->assertSet('search', 'キーワード');

        $component->call('clearSearch');
        $component->assertSet('search', '');
    }

    #[Test]
    public function clear_search_does_not_record_history(): void
    {
        $component = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id]);

        $component->call('executeSearch', 'キーワード');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'searched',
        ]);

        $searchedCount = \App\Models\CustomActivity::where('event', 'searched')->count();

        $component->call('clearSearch');

        // clearSearch は新しい searched イベントを生成しない
        $this->assertSame(
            $searchedCount,
            \App\Models\CustomActivity::where('event', 'searched')->count()
        );
    }

    // ---------------------------------------------------------------
    // Rendering regression: Alpine attributes and HTML structure
    // ---------------------------------------------------------------

    #[Test]
    public function search_form_uses_x_model_not_wire_model(): void
    {
        $html = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->html();

        // x-model (Alpine only, no server sync per keystroke)
        $this->assertStringContainsString('x-model="localSearch"', $html);
        // wire:model は存在しない（キー入力ごとの台帳検索を防ぐ）
        $this->assertStringNotContainsString('wire:model="search"', $html);
    }

    #[Test]
    public function search_form_has_custom_clear_button(): void
    {
        $html = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->html();

        // Mary UI clearable 非使用の独自クリアボタン
        $this->assertStringContainsString('clearSearch', $html);
        $this->assertStringContainsString('localSearch', $html);
    }

    #[Test]
    public function search_form_has_enter_and_blur_handlers(): void
    {
        $html = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->html();

        // 入力中サジェスト制御
        $this->assertStringContainsString('onLocalInput()', $html);
        $this->assertStringContainsString('commitSuggestions', $html);
    }

    #[Test]
    public function dropdown_sections_use_blade_translations_not_js(): void
    {
        $html = Livewire::test(IndexManager::class, ['folderId' => $this->folder->id])
            ->html();

        // 翻訳文字列は Blade @js(__()) 直接埋め込み
        $this->assertStringContainsString('最近の検索', $html);
        $this->assertStringContainsString('関連する検索', $html);
        $this->assertStringContainsString('よく検索されている', $html);
        // JS getSectionTitle 不使用
        $this->assertStringNotContainsString('getSectionTitle', $html);
    }
}
