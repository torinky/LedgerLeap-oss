<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\IndexManager; // RecordsTable から IndexManager へ変更
use App\Models\AutoLink;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class RecordsTableQueryTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private LedgerDefine $ledgerDefine;

    private Folder $folder;

    protected \App\Models\Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = \App\Models\Tenant::create(['id' => 'test-'.uniqid()]);
        tenancy()->initialize($this->tenant);

        // Use a unique email for each test to avoid constraint violations
        $this->user = User::factory()->create([
            'email' => 'test.'.\Illuminate\Support\Str::random(10).'@example.com',
        ]);

        // The component expects a root folder to exist - use factory without fixed ID
        $rootFolder = Folder::factory()->create(['parent_id' => null]);
        $this->folder = Folder::factory()->create(['parent_id' => $rootFolder->id]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'column_define' => [
                ['id' => 0, 'name' => 'テキストカラム', 'type' => 'text', 'order' => 1, 'display_level' => 1],
                // Add other column definitions as needed for other tests
            ],
        ]);

        $this->actingAs($this->user);

        // Add permission for the user to view LedgerDefines
        Permission::firstOrCreate(['name' => 'view_ledger_defines', 'guard_name' => 'web']);
        // Add permission for the user to view Ledgers
        Permission::firstOrCreate(['name' => 'ledgerView', 'guard_name' => 'web']);
        // Add permission for the user to view AutoLinks (追加)
        Permission::firstOrCreate(['name' => 'view_auto_links', 'guard_name' => 'web']);

        $this->user->givePermissionTo(['view_ledger_defines', 'ledgerView', 'view_auto_links']);
    }

    protected function getTablesToTruncate(): array
    {
        return [
            'folders',
            'ledgers',
            'ledger_defines',
            'auto_links',
            'personal_access_tokens',
        ];
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
        parent::tearDown();
    }

    #[Test]
    public function it_shows_list_on_multiple_matches()
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['common-term'],
            'tenant_id' => $this->tenant->id,
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['common-term'],
            'tenant_id' => $this->tenant->id,
        ]);

        Livewire::withQueryParams([
            'q' => 'common-term',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])
            ->test(IndexManager::class) // IndexManager を対象に
            ->assertOk()
            ->assertSee('common-term');
    }

    #[Test]
    public function it_shows_list_on_zero_matches()
    {
        Livewire::withQueryParams([
            'q' => 'non-existent-term',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])
            ->test(IndexManager::class) // IndexManager を対象に
            ->assertOk()
            ->assertSee(__('ledger.select_message'));
    }

    #[Test]
    public function it_forces_list_view_on_unique_match_with_mode_list()
    {
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => ['unique-id-for-list'],
        ]);

        Livewire::withQueryParams([
            'q' => 'unique-id-for-list',
            'mode' => 'list',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])
            ->test(IndexManager::class) // IndexManager を対象に
            ->assertOk()
            ->assertSee('unique-id-for-list');
    }

    #[Test]
    public function it_highlights_keywords_in_list_view()
    {
        // テストデータの準備
        $keyword = 'テストキーワード';
        $contentWithKeyword = [0 => 'これは'.$keyword.'を含むテキストです。'];
        Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => $contentWithKeyword,
        ]);

        // Livewireコンポーネントのテスト
        Livewire::withQueryParams([
            'q' => $keyword,
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])
            ->test(IndexManager::class) // IndexManager を対象に
            ->assertOk()
            ->assertSeeHtml('<mark class="text-error font-bold text-lg">'.$keyword.'</mark>');
    }

    #[Test]
    public function it_displays_auto_links_in_list_view()
    {
        // AutoLinkの準備
        $autoLink = \App\Models\AutoLink::factory()->create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Test Link',
            'pattern' => '/(SPEC\d{3})/',
            'url_template' => '/l/$1',
            'is_enabled' => true,
        ]);

        // AutoLinkScopeを作成してフォルダにスコープを限定
        \App\Models\AutoLinkScope::create([
            'auto_link_id' => $autoLink->id,
            'scopeable_type' => (new Folder)->getMorphClass(),
            'scopeable_id' => $this->folder->id,
        ]);

        // キャッシュクリア
        \Illuminate\Support\Facades\Cache::tags('auto_links')->flush();

        // 台帳データの準備
        $autoLinkText = 'これはSPEC007を含むテキストです。';
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => $autoLinkText],
        ]);

        // Livewireコンポーネントのテスト
        $component = Livewire::actingAs($this->user)
            ->test(\App\Livewire\Ledger\RecordsTable::class, [
                'currentFolderId' => $this->folder->id,
                'selectedFolderIds' => [$this->folder->id],
                'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
                'search' => 'SPEC',
            ]);

        $component->assertOk()
            ->assertSeeHtml('<a href="/l/SPEC007"');
    }

    #[Test]
    public function it_calls_rag_search_service_when_semantic_search_is_selected()
    {
        // RagSearchServiceをモック
        $this->mock(\App\Services\RagSearchService::class, function ($mock) {
            $mock->shouldReceive('searchLedgers')
                ->once()
                ->with(
                    \Mockery::any(),
                    \Mockery::any(),
                    \Mockery::any()
                )
                ->andReturn([]);
        });

        Livewire::withQueryParams([
            'q' => 'semantic query',
            'f' => [$this->folder->id],
            'l' => [$this->ledgerDefine->id],
            'cf' => $this->folder->id,
        ])
            ->test(IndexManager::class)
            ->set('useSemanticSearch', true)
            ->set('search', 'semantic query')
            ->assertOk();
    }
}
