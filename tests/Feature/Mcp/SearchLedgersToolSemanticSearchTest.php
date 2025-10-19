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

    private static bool $isDataSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        if (!self::$isDataSeeded) {
            // 1. デモデータを準備
            Artisan::call('db:seed', ['--class' => 'DemoCompleteSeeder']);
            Artisan::call('rag:chunk-demo-ledgers');
            self::$isDataSeeded = true;
        }

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
}
