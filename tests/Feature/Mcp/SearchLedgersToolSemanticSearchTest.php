<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Tools\SearchLedgersTool;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\RagSearchService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Traits\RefreshDatabaseWithTenant;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Mockery;
use Tests\TestCase;

class SearchLedgersToolSemanticSearchTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // 1. デモデータを準備
        Artisan::call('db:seed', ['--class' => 'DemoCompleteSeeder']);
        Artisan::call('rag:chunk-demo-ledgers');

        $this->user = User::where('email', 'admin@example.com')->first();
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

    #[Group("semantic-search")]
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
    #[Group("semantic-search")]
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

    #[Group("semantic-search")]
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
    #[Group("semantic-search")]
    public function it_finds_semantically_similar_ledger_even_if_keywords_do_not_match()
    {
        //1件にヒットさせるために意図的に調整
        config(['rag.similarity_threshold' => 0.15]);

        // Arrange
        // 1. テストデータを作成
        $ledgerDefine = \App\Models\LedgerDefine::where('title', '[DEMO] 営業日報')->first();
        $folder = \App\Models\Folder::where('title', '日報')->first();

        // 認証ユーザーを設定
        $this->actingAs($this->user);

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

        // 2. 作成した台帳をベクトル化
        Artisan::call('rag:chunk-existing-ledgers', ['--no-interaction' => true]);

        // 3. 検索ツールを準備
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
        $this->assertCount(1, $result['ledgers'], "Expected to find 1 ledger, but found ".count($result['ledgers']));
        $this->assertEquals($ledger->id, $result['ledgers'][0]['id'], "The found ledger ID does not match the created one.");
    }
}
