<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\WorkflowStatus;
use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\RelatedLedgers;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RagSearchService;
use App\Services\UserService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * 関連案件タブ（RelatedLedgers）のバックエンドロジックテスト
 *
 * CI考慮事項:
 * - RefreshDatabase: 各テスト後にDBをロールバックし、テスト間のデータ汚染を防止
 * - Queue::fake(): TestCase基底クラスの fakeQueue=true により自動適用
 *   → Ledger::factory()->create() が LedgerObserver → ProcessLedgerForRagJob を
 *     発火しても Embedding コンテナへの接続は発生しない
 * - RagSearchService のモック: $this->mock() で外部 RAG コンテナへの依存を排除
 * - 全文検索: Mroonga (groonga/mroonga:mysql-8.0-latest) を使用するため
 *   Feature スイートで実行（CI の feature ジョブ対象）
 * - グループ: external / database-migrations に属さないため CI feature ジョブで自動実行される
 */
class RelatedLedgersTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    private Folder $folder;

    private LedgerDefine $define;

    private Ledger $ledger;

    protected Tenant $tenant;

    private bool $originalRagEnabled;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // このクラスは意味検索の挙動を検証するため、RAGを明示的に有効化する
        $this->originalRagEnabled = config('rag.enabled', false);
        config(['rag.enabled' => true]);
        $this->tenant = $this->getTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // 権限設定
        $viewPermission = Permission::firstOrCreate(['name' => 'view_ledgers']);
        $this->user->givePermissionTo($viewPermission);

        $readerRole = Role::firstOrCreate(['name' => 'reader_role']);
        $this->user->assignRole($readerRole);

        $userService = $this->app->make(UserService::class);
        $userService->clearUserPermissionsCache($this->user);

        // auto_number カラムを持つ台帳定義を作成
        $this->folder = Folder::factory()->create();

        RoleFolderPermission::create([
            'role_id' => $readerRole->id,
            'folder_id' => $this->folder->id,
            'permission' => FolderPermissionType::READ->value,
            'modifier_id' => $this->user->id,
        ]);

        $this->define = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                    new ColumnDefine(1, 'タイトル', 'text', 2),
                    new ColumnDefine(2, '説明', 'textarea', 3),
                ],
            ]);

        // テスト対象のレコード（content[0] に auto_number 値を持つ）
        $this->ledger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',
                    1 => 'メインテスト設備',
                    2 => '設備の詳細説明',
                ],
            ]);
    }

    protected function tearDown(): void
    {
        config(['rag.enabled' => $this->originalRagEnabled]);
        parent::tearDown();
    }

    // ─────────────────────────────────────────────
    // Task 1.1.1: auto_number カラム値の抽出
    // ─────────────────────────────────────────────

    #[Test]
    public function it_extracts_auto_number_values_from_ledger(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $values = $component->extractAutoNumberValues($this->ledger);

        $this->assertIsArray($values);
        $this->assertArrayHasKey('EQ-001', $values);
        $this->assertSame('auto_number', $values['EQ-001']['source']);
        // text タイプの値はキーに含まれない
        $this->assertArrayNotHasKey('メインテスト設備', $values);
    }

    #[Test]
    public function it_returns_empty_when_no_auto_number_columns(): void
    {
        // auto_number カラムを持たない台帳定義
        $defineWithoutAutoNumber = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, 'タイトル', 'text', 1),
                    new ColumnDefine(1, '内容', 'textarea', 2),
                ],
            ]);

        $ledgerWithoutAutoNumber = Ledger::factory()
            ->for($defineWithoutAutoNumber, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => '普通のテキスト',
                    1 => '説明文',
                ],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $ledgerWithoutAutoNumber->id;

        $values = $component->extractAutoNumberValues($ledgerWithoutAutoNumber);

        $this->assertIsArray($values);
        $this->assertEmpty($values);
    }

    // ─────────────────────────────────────────────
    // Task 1.2.2 + 1.3.1: 識別番号検索
    // ─────────────────────────────────────────────

    #[Test]
    public function it_finds_related_ledgers_by_identifier(): void
    {
        // 同じ識別番号 'EQ-001' を content に含む別レコードを作成
        $relatedLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',      // 同じ識別番号
                    1 => '関連設備記録',
                    2 => '関連する設備の説明',
                ],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001' => ['source' => 'auto_number', 'column' => '管理番号']]);

        $this->assertGreaterThan(0, $results->count());
        // 戻り値は array{ledger, matched_keys} の Collection
        $resultIds = $results->pluck('ledger.id')->toArray();
        $this->assertContains($relatedLedger->id, $resultIds);
    }

    #[Test]
    public function it_excludes_self_from_identifier_search(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001' => ['source' => 'auto_number', 'column' => '管理番号']]);

        $resultIds = $results->pluck('ledger.id')->toArray();
        $this->assertNotContains($this->ledger->id, $resultIds);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_identifiers_given(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers([]);

        $this->assertTrue($results->isEmpty());
    }

    #[Test]
    public function it_filters_out_results_from_inaccessible_folders(): void
    {
        // 別フォルダ（権限なし）の台帳定義
        $restrictedFolder = Folder::factory()->create();
        $restrictedDefine = LedgerDefine::factory()
            ->for($restrictedFolder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                ],
            ]);

        // そのフォルダに同じ識別番号のレコードを作成
        Ledger::factory()
            ->for($restrictedDefine, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [0 => 'EQ-001'],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001' => ['source' => 'auto_number', 'column' => '管理番号']]);

        // 権限のないフォルダのレコードは含まれない
        $resultFolderIds = $results->map(fn ($item) => $item['ledger']->define->folder_id ?? null)->unique()->toArray();
        $this->assertNotContains($restrictedFolder->id, $resultFolderIds);
    }

    // ─────────────────────────────────────────────
    // Sprint 2 用: 意味検索クエリ生成（バックエンドロジックのみ）
    // ─────────────────────────────────────────────

    #[Test]
    public function it_builds_semantic_query_excluding_files_columns(): void
    {
        $defineWithFiles = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, 'タイトル', 'text', 1),
                    new ColumnDefine(1, '添付', 'files', 2),
                    new ColumnDefine(2, '説明', 'textarea', 3),
                ],
            ]);

        $ledgerWithFiles = Ledger::factory()
            ->for($defineWithFiles, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => '設備点検記録',
                    1 => [['original_name' => 'photo.jpg']], // files タイプ
                    2 => '詳細な点検内容をここに記載',
                ],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $ledgerWithFiles->id;

        $query = $component->buildSemanticQuery($ledgerWithFiles);

        $this->assertNotEmpty($query);
        // files カラムの値は含まれない
        $this->assertStringNotContainsString('photo.jpg', $query);
        // テキスト値は含まれる
        $this->assertStringContainsString('設備点検記録', $query);
        $this->assertStringContainsString('詳細な点検内容', $query);
    }

    #[Test]
    public function it_handles_rag_service_unavailable_gracefully(): void
    {
        // RagSearchService が例外を投げる状況をモック
        $this->mock(RagSearchService::class, function ($mock) {
            $mock->shouldReceive('searchLedgers')->andThrow(new \RuntimeException('RAG service unavailable'));
        });

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        // 例外が外部に伝播しないこと
        $results = $component->searchBySemantic($this->ledger);

        $this->assertTrue($results->isEmpty());
        $this->assertFalse($component->ragAvailable);
    }

    // ─────────────────────────────────────────────
    // Sprint 2: 意味検索の詳細テスト
    // ─────────────────────────────────────────────

    #[Test]
    public function it_finds_related_ledgers_by_semantic_search(): void
    {
        // 関連するレコードを作成
        $relatedLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-002',
                    1 => '設備点検報告書',
                    2 => '設備の定期点検を実施した記録',
                ],
            ]);

        // RagSearchService が関連レコードを返すようにモック
        // 戻り値の形式: [['ledger_id' => id, 'max_score' => float, ...], ...]
        $this->mock(RagSearchService::class, function ($mock) use ($relatedLedger) {
            $mock->shouldReceive('searchLedgers')
                ->once()
                ->andReturn([
                    [
                        'ledger_id' => $relatedLedger->id,
                        'max_score' => 0.95,
                        'best_chunk_text' => '設備の定期点検を実施した記録',
                        'chunk_count' => 1,
                    ],
                ]);
        });

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchBySemantic($this->ledger);

        $this->assertFalse($results->isEmpty());
        $resultIds = $results->pluck('ledger.id')->toArray();
        $this->assertContains($relatedLedger->id, $resultIds);
        $this->assertTrue($component->ragAvailable);
    }

    #[Test]
    public function it_uses_passage_prefix_and_related_limit_for_semantic_search(): void
    {
        $relatedLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-003',
                    1 => '設備関連レコード',
                    2 => '関連案件の意味検索確認用',
                ],
            ]);

        $semanticLimit = config('rag.related_ledger.semantic_limit', 10);

        $this->mock(RagSearchService::class, function ($mock) use ($relatedLedger, $semanticLimit) {
            $mock->shouldReceive('searchLedgers')
                ->once()
                ->with(
                    Mockery::type('string'),
                    $semanticLimit,
                    Mockery::on(function (array $filters) {
                        return isset($filters['user']) && $filters['user'] instanceof User;
                    }),
                    'passage'
                )
                ->andReturn([
                    [
                        'ledger_id' => $relatedLedger->id,
                        'max_score' => 0.91,
                        'best_chunk_text' => '関連案件の意味検索確認用',
                        'chunk_count' => 1,
                    ],
                ]);
        });

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchBySemantic($this->ledger);

        $this->assertFalse($results->isEmpty());
        $this->assertSame($relatedLedger->id, $results->first()['ledger']->id);
        $this->assertTrue($component->ragAvailable);
    }

    #[Test]
    public function it_excludes_self_from_semantic_search(): void
    {
        // RagSearchService が自身も含む結果を返すモック
        $this->mock(RagSearchService::class, function ($mock) {
            $mock->shouldReceive('searchLedgers')
                ->once()
                ->andReturn([
                    [
                        'ledger_id' => $this->ledger->id, // 自身
                        'max_score' => 1.0,
                        'best_chunk_text' => 'メインテスト設備',
                        'chunk_count' => 1,
                    ],
                ]);
        });

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchBySemantic($this->ledger);

        // 自身は結果から除外される
        $resultIds = $results->pluck('ledger.id')->toArray();
        $this->assertNotContains($this->ledger->id, $resultIds);
    }

    #[Test]
    public function it_returns_empty_when_semantic_query_is_empty(): void
    {
        // コンテンツが全て空のレコード
        $emptyLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [0 => '', 1 => '', 2 => ''],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $emptyLedger->id;

        $query = $component->buildSemanticQuery($emptyLedger);
        $this->assertEmpty($query);

        // クエリが空のため searchBySemantic も空を返す（RAGを呼ばない）
        $results = $component->searchBySemantic($emptyLedger);
        $this->assertTrue($results->isEmpty());
    }

    // ─────────────────────────────────────────────
    // Sprint 3: マージ・識別理由付与
    // ─────────────────────────────────────────────

    #[Test]
    public function it_merges_identifier_and_semantic_results_with_correct_reason(): void
    {
        $identifierLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-010', 1 => '識別番号専用', 2 => '']]);

        $semanticLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-020', 1 => '意味検索専用', 2 => '']]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $merged = $component->mergeResults(
            collect([['ledger' => $identifierLedger, 'matched_keys' => ['EQ-010']]]),
            collect([['ledger' => $semanticLedger,   'score' => 0.85]])
        );

        $this->assertCount(2, $merged);

        $identifierReason = collect($merged)->firstWhere(fn ($item) => $item['ledger']->id === $identifierLedger->id);
        $semanticReason = collect($merged)->firstWhere(fn ($item) => $item['ledger']->id === $semanticLedger->id);

        $this->assertSame('identifier', $identifierReason['reason']);
        $this->assertSame('semantic', $semanticReason['reason']);
    }

    #[Test]
    public function it_marks_both_when_ledger_appears_in_both_searches(): void
    {
        $bothLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-001', 1 => '両方ヒット', 2 => '詳細説明']]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $merged = $component->mergeResults(
            collect([['ledger' => $bothLedger, 'matched_keys' => ['EQ-001']]]),
            collect([['ledger' => $bothLedger, 'score' => 0.92]])
        );

        $this->assertCount(1, $merged);
        $this->assertSame('both', $merged[0]['reason']);
        $this->assertSame($bothLedger->id, $merged[0]['ledger']->id);
        $this->assertSame(0.92, $merged[0]['score']);
    }

    // ─────────────────────────────────────────────
    // Sprint 3: フィルタリング
    // ─────────────────────────────────────────────

    #[Test]
    public function it_filters_by_show_identifier_toggle(): void
    {
        $identifierLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-031', 1 => '識別番号のみ', 2 => '']]);
        $semanticLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-032', 1 => '意味検索のみ', 2 => '']]);

        $merged = [
            ['ledger' => $identifierLedger, 'reason' => 'identifier', 'score' => null, 'matched_keys' => []],
            ['ledger' => $semanticLedger, 'reason' => 'semantic', 'score' => null, 'matched_keys' => []],
        ];

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;
        $component->showIdentifier = false;  // 識別番号をオフ
        $component->showSemantic = true;

        $filtered = $component->applyFilter($merged);

        $this->assertCount(1, $filtered);
        $this->assertSame('semantic', $filtered[0]['reason']);
    }

    #[Test]
    public function it_filters_by_show_semantic_toggle(): void
    {
        $identifierLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-041', 1 => '識別番号のみ', 2 => '']]);
        $semanticLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-042', 1 => '意味検索のみ', 2 => '']]);
        $bothLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-043', 1 => '両方', 2 => '']]);

        $merged = [
            ['ledger' => $identifierLedger, 'reason' => 'identifier', 'score' => null, 'matched_keys' => []],
            ['ledger' => $semanticLedger, 'reason' => 'semantic', 'score' => null, 'matched_keys' => []],
            ['ledger' => $bothLedger, 'reason' => 'both', 'score' => 0.9, 'matched_keys' => []],
        ];

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;
        $component->showIdentifier = true;
        $component->showSemantic = false;  // 意味検索をオフ

        $filtered = $component->applyFilter($merged);

        $this->assertCount(2, $filtered);  // identifier + both（どちらかオンなので表示）
        $reasons = array_column($filtered, 'reason');
        $this->assertContains('identifier', $reasons);
        $this->assertContains('both', $reasons);
        $this->assertNotContains('semantic', $reasons);
    }

    #[Test]
    public function it_updates_display_level_from_event(): void
    {
        $component = Livewire::test(RelatedLedgers::class, ['ledgerId' => $this->ledger->id]);

        $component->assertSet('displayLevel', 1);
        $component->dispatch('displayLevelUpdated', displayLevel: 3);
        $component->assertSet('displayLevel', 3);
    }

    #[Test]
    public function it_targets_update_display_level_in_loading_overlay(): void
    {
        $component = Livewire::withoutLazyLoading()->test(RelatedLedgers::class, ['ledgerId' => $this->ledger->id]);

        $this->assertStringContainsString(
            'wire:target="showIdentifier,showSemantic,displayLevel"',
            $component->html()
        );
    }

    #[Test]
    public function it_disables_resize_observation_for_related_tab_rows(): void
    {
        $relatedLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',
                    1 => str_repeat('関連案件タブの表示確認用テキスト', 4),
                    2 => '説明',
                ],
                'status' => WorkflowStatus::NONE,
            ]);
        $relatedLedger->load('define');

        $view = $this->blade(
            <<<'BLADE'
<x-ledger.table-row
    :ledger-record="$ledger"
    :highlight-keyword="null"
    :can-update="false"
    :can-view="true"
    :all-attachments="collect()"
    :filtered-column-defines="$filteredColumnDefines"
    :current-tenant-id="$currentTenantId"
    :related-badge="null"
    :selected-file-id="null"
    :selected-ledger-id="null"
    :selected-column-id="null"
    :expandable-observe-resize="false"
/>
BLADE,
            [
                'ledger' => $relatedLedger,
                'filteredColumnDefines' => $this->define->column_define,
                'currentTenantId' => $this->tenant->id,
            ]
        );

        $view->assertSee('observeResize: false', false);
    }

    // ─────────────────────────────────────────────
    // Sprint 3: ページング
    // ─────────────────────────────────────────────

    #[Test]
    public function it_paginates_merged_results(): void
    {
        // 25件のアイテムを生成（デフォルト perPage=20 なので2ページに分かれる）
        $items = [];
        for ($i = 0; $i < 25; $i++) {
            $l = Ledger::factory()
                ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
                ->create(['content' => [0 => "EQ-{$i}", 1 => "テスト{$i}", 2 => '']]);
            $items[] = ['ledger' => $l, 'reason' => 'identifier', 'score' => null, 'matched_keys' => []];
        }

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $paginator = $component->buildPaginator($items);

        $this->assertSame(25, $paginator->total());
        $this->assertCount(20, $paginator->items());  // 1ページ目は20件
        $this->assertTrue($paginator->hasMorePages());
        $this->assertSame('related_page', $paginator->getPageName());
    }

    // ─────────────────────────────────────────────
    // Sprint 3: グルーピング
    // ─────────────────────────────────────────────

    #[Test]
    public function it_groups_results_by_ledger_define(): void
    {
        // 別台帳定義を作成
        $otherDefine = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                    new ColumnDefine(1, 'タイトル', 'text', 2),
                ],
            ]);

        $ledger1 = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-051', 1 => '台帳Aのレコード', 2 => '']]);
        $ledger2 = Ledger::factory()
            ->for($otherDefine, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-052', 1 => '台帳Bのレコード']]);

        $items = [
            ['ledger' => $ledger1, 'reason' => 'identifier', 'score' => null, 'matched_keys' => []],
            ['ledger' => $ledger2, 'reason' => 'semantic', 'score' => null, 'matched_keys' => []],
        ];

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $grouped = $component->groupByDefine($items);

        $this->assertCount(2, $grouped);  // 2グループ
        $this->assertTrue($grouped->has($this->define->id));
        $this->assertTrue($grouped->has($otherDefine->id));
        $this->assertCount(1, $grouped[$this->define->id]);
        $this->assertCount(1, $grouped[$otherDefine->id]);
    }

    // ─────────────────────────────────────────────
    // Livewire コンポーネントレンダリング（Sprint 4 ビュー作成後に有効化）
    // ─────────────────────────────────────────────

    #[Test]
    public function it_can_be_instantiated_as_livewire_component(): void
    {
        $this->expectNotToPerformAssertions();

        try {
            Livewire::test(RelatedLedgers::class, ['ledgerId' => $this->ledger->id]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'View') && str_contains($e->getMessage(), 'not found')) {
                $this->markTestSkipped('Placeholder/main view not yet created (Sprint 4 task)');
            }
            throw $e;
        }
    }

    // ─────────────────────────────────────────────
    // Sprint 6: スコア保持・matched_keys保持・ソート
    // ─────────────────────────────────────────────

    #[Test]
    public function it_retains_score_from_semantic_search(): void
    {
        $relatedLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-099', 1 => 'スコアテスト', 2 => '']]);

        $this->mock(RagSearchService::class, function ($mock) use ($relatedLedger) {
            $mock->shouldReceive('searchLedgers')->once()->andReturn([
                ['ledger_id' => $relatedLedger->id, 'max_score' => 0.87, 'best_chunk_text' => '', 'chunk_count' => 1],
            ]);
        });

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchBySemantic($this->ledger);

        $this->assertFalse($results->isEmpty());
        $item = $results->first();
        $this->assertArrayHasKey('score', $item);
        $this->assertEqualsWithDelta(0.87, $item['score'], 0.001);
    }

    #[Test]
    public function it_retains_matched_keys_from_identifier_search(): void
    {
        // 同じ識別番号を持つ別レコードを作成
        Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-001', 1 => 'matched_keysテスト', 2 => '']]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001' => ['source' => 'auto_number', 'column' => '管理番号']]);

        $this->assertFalse($results->isEmpty());
        $item = $results->first();
        $this->assertArrayHasKey('matched_keys', $item);
        // matched_keys は {value, source, column} の配列
        $matchedValues = array_column($item['matched_keys'], 'value');
        $this->assertContains('EQ-001', $matchedValues);
        $this->assertSame('auto_number', $item['matched_keys'][0]['source']);
    }

    #[Test]
    public function it_sorts_by_score_descending_with_identifier_last(): void
    {
        $ledgerA = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-A', 1 => 'ハイスコア', 2 => '']]);
        $ledgerB = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-B', 1 => 'ロースコア', 2 => '']]);
        $ledgerC = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-C', 1, '識別番号のみ', 2 => '']]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $merged = $component->mergeResults(
            collect([['ledger' => $ledgerC, 'matched_keys' => ['EQ-C']]]),       // identifier のみ
            collect([
                ['ledger' => $ledgerA, 'score' => 0.95],
                ['ledger' => $ledgerB, 'score' => 0.60],
            ])
        );

        // 期待するソート順: A(0.95) → B(0.60) → C(null=identifier のみ)
        $this->assertSame($ledgerA->id, $merged[0]['ledger']->id);
        $this->assertSame($ledgerB->id, $merged[1]['ledger']->id);
        $this->assertSame($ledgerC->id, $merged[2]['ledger']->id);
    }

    #[Test]
    public function it_marks_both_score_when_ledger_appears_in_both(): void
    {
        $bothLedger = Ledger::factory()
            ->for($this->define, 'define')->for($this->user, 'creator')->for($this->user, 'modifier')
            ->create(['content' => [0 => 'EQ-001', 1 => '両方テスト', 2 => '']]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $merged = $component->mergeResults(
            collect([['ledger' => $bothLedger, 'matched_keys' => ['EQ-001']]]),
            collect([['ledger' => $bothLedger, 'score' => 0.75]])
        );

        $this->assertCount(1, $merged);
        $this->assertSame('both', $merged[0]['reason']);
        $this->assertEqualsWithDelta(0.75, $merged[0]['score'], 0.001);
        $this->assertContains('EQ-001', $merged[0]['matched_keys']);
    }

    // ─────────────────────────────────────────────
    // Sprint C (Issue #76): パターンB — テキスト列識別番号抽出
    // ─────────────────────────────────────────────

    #[Test]
    public function it_extracts_identifier_from_text_column(): void
    {
        // prefix='EQ-', digits=3 の auto_number カラムを持つ台帳定義を作成
        // → 'EQ-001' にマッチするパターン '/( EQ-\d{3,})(?![0-9])/u' が生成される
        $defineWithPrefix = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '設備番号', 'auto_number', 1, ['prefix' => 'EQ-', 'digits' => 3, 'revision' => '']),
                    new ColumnDefine(1, '作業内容', 'text', 2),
                ],
            ]);

        // テキスト列に 'EQ-001' を含むレコードを作成
        $ledgerWithTextRef = Ledger::factory()
            ->for($defineWithPrefix, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => '',                             // auto_number 列は空
                    1 => 'EQ-001 の修理作業を実施した', // text 列に識別番号が含まれる
                ],
            ]);

        // キャッシュをクリアして確実に最新パターンを取得
        Cache::tags(['auto_links'])->flush();

        $component = new RelatedLedgers;
        $component->ledgerId = $ledgerWithTextRef->id;

        $values = $component->extractAutoNumberValues($ledgerWithTextRef);

        // テキスト列から EQ-001 が抽出される（パターンB）
        $this->assertArrayHasKey('EQ-001', $values);
        $this->assertSame('text_column', $values['EQ-001']['source']);
        $this->assertSame('作業内容', $values['EQ-001']['column']);
    }

    #[Test]
    public function it_does_not_extract_from_files_or_auto_number_column(): void
    {
        // files / auto_number 型列はパターンBの対象外
        $defineWithFiles = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '管理番号', 'auto_number', 1),
                    new ColumnDefine(1, '添付', 'files', 2),
                    new ColumnDefine(2, '備考', 'textarea', 3),
                ],
            ]);

        $ledger = Ledger::factory()
            ->for($defineWithFiles, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',                           // auto_number 列 → パターンA
                    1 => [['original_name' => 'EQ-001.pdf']], // files 列 → 除外
                    2 => '通常のメモ（識別番号なし）',
                ],
            ]);

        Cache::tags(['auto_links'])->flush();

        $component = new RelatedLedgers;
        $component->ledgerId = $ledger->id;

        $values = $component->extractAutoNumberValues($ledger);

        // auto_number 列の値はパターンAとして取得
        $this->assertArrayHasKey('EQ-001', $values);
        $this->assertSame('auto_number', $values['EQ-001']['source']);

        // files 列のファイル名から抽出されていないこと（EQ-001 が2重登録されていないこと）
        // → パターンAで先に取得済みのため、text_column で上書きされないこと
        $this->assertNotSame('text_column', $values['EQ-001']['source']);
    }

    #[Test]
    public function it_searches_by_text_column_identifier(): void
    {
        // prefix='EQ-' の auto_number カラムを持つ台帳定義
        $defineWithPrefix = LedgerDefine::factory()
            ->for($this->folder)
            ->create([
                'column_define' => [
                    new ColumnDefine(0, '設備番号', 'auto_number', 1, ['prefix' => 'EQ-', 'digits' => 3, 'revision' => '']),
                    new ColumnDefine(1, '作業内容', 'text', 2),
                ],
            ]);

        // テキスト列に識別番号が記載されたレコード（パターンB抽出元）
        $sourceledger = Ledger::factory()
            ->for($defineWithPrefix, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => '',
                    1 => 'EQ-001 に関連する作業を実施',  // text 列に識別番号
                ],
            ]);

        // EQ-001 を auto_number 列に持つ別レコード（検索でヒットする側）
        $targetLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',
                    1 => '設備台帳',
                    2 => '',
                ],
            ]);

        Cache::tags(['auto_links'])->flush();

        $component = new RelatedLedgers;
        $component->ledgerId = $sourceledger->id;

        // extractAutoNumberValues でパターンBとして EQ-001 が抽出されることを確認
        $values = $component->extractAutoNumberValues($sourceledger);
        $this->assertArrayHasKey('EQ-001', $values);
        $this->assertSame('text_column', $values['EQ-001']['source']);

        // searchByIdentifiers で targetLedger がヒットすることを確認
        $results = $component->searchByIdentifiers($values);
        $resultIds = $results->pluck('ledger.id')->toArray();
        $this->assertContains($targetLedger->id, $resultIds);
    }

    #[Test]
    public function it_marks_source_as_text_column_in_matched_keys(): void
    {
        // パターンBで抽出した識別番号での検索結果に source='text_column' が付与されること
        $targetLedger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [0 => 'EQ-001', 1 => '設備台帳', 2 => ''],
            ]);

        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers([
            'EQ-001' => ['source' => 'text_column', 'column' => '作業内容'],
        ]);

        $this->assertFalse($results->isEmpty());
        $item = $results->first();
        $matchedKeys = $item['matched_keys'];

        $this->assertNotEmpty($matchedKeys);
        $this->assertSame('EQ-001', $matchedKeys[0]['value']);
        $this->assertSame('text_column', $matchedKeys[0]['source']);
        $this->assertSame('作業内容', $matchedKeys[0]['column']);
    }

    #[Test]
    public function it_deduplicates_keys_across_pattern_a_and_b(): void
    {
        // 同じ値がパターンA（auto_number 列）とパターンB（text 列）の両方に存在する場合、
        // パターンAが優先され、パターンBで上書きされないこと
        $ledger = Ledger::factory()
            ->for($this->define, 'define')
            ->for($this->user, 'creator')
            ->for($this->user, 'modifier')
            ->create([
                'content' => [
                    0 => 'EQ-001',                    // auto_number 列（パターンA）
                    1 => 'EQ-001 の追加作業を実施',   // text 列（パターンB）
                    2 => '',
                ],
            ]);

        Cache::tags(['auto_links'])->flush();

        $component = new RelatedLedgers;
        $component->ledgerId = $ledger->id;

        $values = $component->extractAutoNumberValues($ledger);

        // EQ-001 はパターンAとパターンBの両方に現れるが、重複排除されて1件のみ
        $eq001Entries = array_filter($values, fn ($info) => true, ARRAY_FILTER_USE_KEY);
        $this->assertArrayHasKey('EQ-001', $eq001Entries);
        $this->assertCount(1, array_filter(array_keys($values), fn ($k) => $k === 'EQ-001'));

        // パターンAが優先される（source = 'auto_number'）
        $this->assertSame('auto_number', $values['EQ-001']['source']);
    }
}
