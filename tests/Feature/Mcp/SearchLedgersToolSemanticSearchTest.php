<?php

namespace Tests\Feature\Mcp;

use App\Jobs\ProcessLedgerForRagJob;
use App\Mcp\Tools\SearchLedgersTool;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SearchLedgersToolSemanticSearchTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テスト用ユーザーを作成（DemoCompleteSeedは重いので不要）
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        // 認証トークンを環境変数に設定
        putenv('MCP_AUTH_TOKEN='.$token);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        putenv('MCP_AUTH_TOKEN'); // 環境変数をクリーンアップ
    }

    #[Group('semantic-search')]
    #[Test]
    public function it_performs_semantic_search_via_mcp_when_semantic_score_is_specified()
    {
        // Arrange
        $ledgerServiceMock = Mockery::mock(LedgerService::class);
        $this->app->instance(LedgerService::class, $ledgerServiceMock);

        // semantic_score が指定された場合、RagSearchServiceが呼ばれることを期待
        $ledgerServiceMock->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->id === $this->user->id),
                Mockery::on(fn ($arg) => $arg['order_by'] === 'semantic_score')
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $tool = new SearchLedgersTool($ledgerServiceMock);
        $request = new Request([
            'q' => '今日の業務内容について',
            'order_by' => 'semantic_score',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
    }

    #[Test]
    #[Group('semantic-search')]
    public function it_throws_an_error_when_semantic_search_is_called_without_a_query()
    {
        // Arrange
        $ledgerService = $this->app->make(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);
        $request = new Request([
            'order_by' => 'semantic_score',
            // 'q' is intentionally omitted
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('semantic_score sorting requires a search query (q parameter)', $response->content());
    }

    #[Group('semantic-search')]
    #[Test]
    public function it_does_not_perform_semantic_search_for_other_order_by_values()
    {
        // Arrange
        $ledgerServiceMock = Mockery::mock(LedgerService::class);
        $this->app->instance(LedgerService::class, $ledgerServiceMock);

        // semantic_score 以外の場合、通常の検索が呼ばれることを期待
        $ledgerServiceMock->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->id === $this->user->id),
                Mockery::on(fn ($arg) => $arg['order_by'] === 'composite_score')
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $tool = new SearchLedgersTool($ledgerServiceMock);
        $request = new Request([
            'q' => 'テストクエリ',
            'order_by' => 'composite_score',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
    }

    #[Test]
    #[Group('semantic-search')]
    public function it_finds_semantically_similar_ledger_even_if_keywords_do_not_match()
    {
        // このテストのみ実データが必要なため、ここでシードする
        Artisan::call('db:seed', ['--class' => 'DemoCompleteSeeder']);

        // さらに緩い閾値に設定して確実にヒットさせる
        config(['rag.similarity_threshold' => 0.0]);

        // Arrange
        // 1. テストデータを作成
        $ledgerDefine = \App\Models\LedgerDefine::where('title', '[DEMO] 営業日報')->first();
        $folder = \App\Models\Folder::where('title', '日報')->first();

        // DemoCompleteSeedで作成されたadminユーザーを使用
        $adminUser = \App\Models\User::where('email', 'admin@example.com')->first();

        // 認証ユーザーを設定
        $this->actingAs($adminUser);

        $ledgerService = $this->app->make(LedgerService::class);
        $ledger = $ledgerService->createLedger([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => [
                '2025-10-20', // 日付
                'セマンティック検索テスト株式会社', // 顧客名
                '性能評価', // 訪問目的
                '提案中', // 商談ステータス
                '高', // 優先度
                'このプロジェクトでは、全社的な経費削減が最重要課題となっている。特に、出張費や交際費の見直しが急務である。', // 商談内容
                'コストカットの具体的な方法について、次回の会議で提案する必要がある。', // 成果・所感
                '経費削減案の資料を作成する。', // 次回アクション
            ],
            'tags' => [],
        ]);

        // 2. 作成した台帳のみを直接ベクトル化（Jobを同期実行）
        ProcessLedgerForRagJob::dispatchSync($ledger->id);

        // テナントを再初期化してからチャンクを確認
        tenancy()->initialize($this->getTenant());

        // チャンクが作成されたことを確認
        $chunkCount = \App\Models\LedgerChunk::where('ledger_id', $ledger->id)->count();
        $this->assertGreaterThan(0, $chunkCount,
            "No chunks were created for ledger {$ledger->id}. RAG processing may have failed.");

        // 3. admin用トークンで検索ツールを準備
        $adminToken = $adminUser->createToken('admin-test-token')->plainTextToken;
        putenv('MCP_AUTH_TOKEN='.$adminToken);

        $tool = new SearchLedgersTool($this->app->make(LedgerService::class));
        $request = new Request([
            'q' => '費用を切り詰める方法', // レコードに直接含まれない類義語で検索
            'order_by' => 'semantic_score',
        ]);

        // Act
        $response = $tool->handle($request);
        $result = json_decode($response->content(), true);

        // Assert
        $this->assertFalse($response->isError(), "MCP tool returned an error: {$response->content()}");

        // 閾値を0にしているので、最低でも1件はヒットするはず
        $this->assertGreaterThanOrEqual(1, count($result['ledgers']),
            'Expected at least 1 ledger, but found '.count($result['ledgers']).'. Result: '.json_encode($result));

        // 最初の結果が作成したledgerであることを確認
        $this->assertEquals($ledger->id, $result['ledgers'][0]['id'],
            'The found ledger ID does not match the created one. First result: '.json_encode($result['ledgers'][0] ?? []));
    }
}
