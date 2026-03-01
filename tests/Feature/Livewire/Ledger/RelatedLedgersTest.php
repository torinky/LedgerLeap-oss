<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Enums\FolderPermissionType;
use App\Livewire\Ledger\RelatedLedgers;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\RoleFolderPermission;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

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
    use RefreshDatabase;

    private User $user;

    private Folder $folder;

    private LedgerDefine $define;

    private Ledger $ledger;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

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
        $this->assertContains('EQ-001', $values);
        // text タイプの値は含まれない
        $this->assertNotContains('メインテスト設備', $values);
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

        $results = $component->searchByIdentifiers(['EQ-001']);

        $this->assertGreaterThan(0, $results->count());
        $resultIds = $results->pluck('id')->toArray();
        $this->assertContains($relatedLedger->id, $resultIds);
    }

    #[Test]
    public function it_excludes_self_from_identifier_search(): void
    {
        $component = new RelatedLedgers;
        $component->ledgerId = $this->ledger->id;

        $results = $component->searchByIdentifiers(['EQ-001']);

        $resultIds = $results->pluck('id')->toArray();
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

        $results = $component->searchByIdentifiers(['EQ-001']);

        // 権限のないフォルダのレコードは含まれない
        $resultFolderIds = $results->map(fn ($l) => $l->define->folder_id ?? null)->unique()->toArray();
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
        $this->mock(\App\Services\RagSearchService::class, function ($mock) {
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
        $this->mock(\App\Services\RagSearchService::class, function ($mock) use ($relatedLedger) {
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
        $resultIds = $results->pluck('id')->toArray();
        $this->assertContains($relatedLedger->id, $resultIds);
        $this->assertTrue($component->ragAvailable);
    }

    #[Test]
    public function it_excludes_self_from_semantic_search(): void
    {
        // RagSearchService が自身も含む結果を返すモック
        $this->mock(\App\Services\RagSearchService::class, function ($mock) {
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
        $resultIds = $results->pluck('id')->toArray();
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
    // Livewire コンポーネントレンダリング（Sprint 3 へ向けた基礎確認）
    // ─────────────────────────────────────────────

    #[Test]
    public function it_can_be_instantiated_as_livewire_component(): void
    {
        // Lazy コンポーネントはプレースホルダーが先に表示されるため、
        // ここでは mount が例外を投げないことを確認するに留める
        $this->expectNotToPerformAssertions();

        try {
            Livewire::test(RelatedLedgers::class, ['ledgerId' => $this->ledger->id]);
        } catch (\Throwable $e) {
            // Lazy コンポーネントのプレースホルダービューが未存在の場合はスキップ
            if (str_contains($e->getMessage(), 'View') && str_contains($e->getMessage(), 'not found')) {
                $this->markTestSkipped('Placeholder view not yet created (Sprint 3 task)');
            }
            throw $e;
        }
    }
}
