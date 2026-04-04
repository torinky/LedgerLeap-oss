<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\IndexManager; // RecordsTable から IndexManager へ変更
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

        $this->tenant = \App\Models\Tenant::create(['id' => 'test-'.uniqid('', true)]);

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
    public function it_marks_zero_result_counts_as_loaded_in_records_table()
    {
        Livewire::actingAs($this->user)
            ->test(
                \App\Livewire\Ledger\RecordsTable::class,
                [
                    'search' => 'non-existent-term',
                    'selectedFolderIds' => [$this->folder->id],
                    'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
                    'currentFolderId' => $this->folder->id,
                ]
            )
            ->assertOk()
            ->assertSet('totalRecordsLoaded', true)
            ->assertSet('totalRecords', 0);
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
        $ledger = Ledger::factory()->create([
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
            ->assertSeeHtml('<mark class="text-error font-bold text-lg">'.$keyword.'</mark>')
            ->assertSeeHtml('/ledger/'.$ledger->id.'?highlight='.urlencode($keyword));
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
        $mock = $this->mock(\App\Services\RagSearchService::class);
        $mock->shouldReceive('searchLedgers')
            ->once()
            ->withArgs(function ($query, $limit, $filters) {
                // query引数が渡されていることを確認
                return is_string($query) &&
                       is_int($limit) &&
                       is_array($filters);
            })
            ->andReturn([
                // 空配列を返すとセマンティック検索の空結果パスに入る
            ]);

        // RecordsTableを直接テスト
        Livewire::test(\App\Livewire\Ledger\RecordsTable::class, [
            'search' => 'semantic query',
            'selectedLedgerDefineIds' => [$this->ledgerDefine->id],
            'selectedFolderIds' => [$this->folder->id],
            'currentFolderId' => $this->folder->id,
            'useSemanticSearch' => true,
        ])
            ->assertOk()
            ->assertViewHas('totalRecords', 0); // 空結果なので0件
    }

    #[Test]
    public function it_executes_efficient_number_of_queries()
    {
        // 多数のレコードと台帳定義を作成
        $ledgerDefines = LedgerDefine::factory()->count(3)->create(['folder_id' => $this->folder->id]);
        foreach ($ledgerDefines as $ld) {
            Ledger::factory()->count(10)->create(['ledger_define_id' => $ld->id]);
        }

        \Illuminate\Support\Facades\DB::enableQueryLog();

        Livewire::actingAs($this->user)
            ->test(\App\Livewire\Ledger\RecordsTable::class, [
                'currentFolderId' => $this->folder->id,
                'selectedFolderIds' => [$this->folder->id],
                'selectedLedgerDefineIds' => $ledgerDefines->pluck('id')->toArray(),
            ])
            ->assertOk();

        $queryLog = \Illuminate\Support\Facades\DB::getQueryLog();
        $queryCount = count($queryLog);

        // クエリをグループ化して重複を検出
        $queryPatterns = [];
        foreach ($queryLog as $query) {
            $pattern = preg_replace('/\d+/', '?', $query['query']); // 数値をプレースホルダーに
            $pattern = preg_replace('/\s+/', ' ', $pattern); // 空白を正規化
            $queryPatterns[$pattern] = ($queryPatterns[$pattern] ?? 0) + 1;
        }

        // 3回以上発生しているクエリ（N+1の可能性）を出力
        foreach ($queryPatterns as $pattern => $count) {
            if ($count >= 3) {
                error_log(sprintf('N+1 Detected [%dx]: %s', $count, substr($pattern, 0, 150)));
            }
        }

        error_log("Total queries: {$queryCount}");

        // Phase 1-3.5 実績: 52件 → 33件（37%改善）
        // 主要なN+1は解消済み。残りの3件は以下の理由で許容範囲内:
        // - AutoLinkキャッシュの二重構造（Laravelキャッシュ + リクエスト内キャッシュ）
        // - フォルダ階層クエリの一部（テナント初期化やセキュリティチェックに必要）
        // Phase 4では20件以下を目標とする（さらなるキャッシュ最適化）
        $this->assertLessThanOrEqual(35, $queryCount, "Too many queries detected: {$queryCount}");

        \Illuminate\Support\Facades\DB::disableQueryLog();
    }
}
