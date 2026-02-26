<?php

namespace Tests\Feature\Api;

use App\Mcp\Tools\SearchLedgersTool;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SearchLedgersToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private User $user;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->user = User::factory()->create();

        // Create Sanctum token for MCP authentication
        $token = $this->user->createToken('mcp-test-token', ['mcp:*']);
        putenv('MCP_AUTH_TOKEN='.$token->plainTextToken);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN');
        parent::tearDown();
    }

    #[Test]
    public function it_calls_ledger_service_with_semantic_score_parameter()
    {
        $this->mock(LedgerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('searchLedgersForApi')
                ->once()
                ->withArgs(function ($user, $params) {
                    return $user->id === $this->user->id &&
                           isset($params['q']) && $params['q'] === 'test query' &&
                           isset($params['order_by']) && $params['order_by'] === 'semantic_score';
                })
                ->andReturn(['ledgers' => [], 'total' => 0, 'meta' => []]);
        });

        $tool = app(SearchLedgersTool::class);
        $request = new Request([
            'q' => 'test query',
            'order_by' => 'semantic_score',
        ]);

        $response = $tool->handle($request);

        $this->assertFalse($response->isError());
    }

    #[Test]
    public function it_does_not_pass_semantic_score_for_other_sort_orders()
    {
        $this->mock(LedgerService::class, function (MockInterface $mock) {
            $mock->shouldReceive('searchLedgersForApi')
                ->once()
                ->withArgs(function ($user, $params) {
                    return ($params['order_by'] ?? 'composite_score') !== 'semantic_score';
                })
                ->andReturn(['ledgers' => [], 'total' => 0, 'meta' => []]);
        });

        $tool = app(SearchLedgersTool::class);
        $request = new Request([
            'q' => 'test query',
            'order_by' => 'created_at',
        ]);

        $response = $tool->handle($request);

        $this->assertFalse($response->isError());
    }

    #[Test]
    public function it_returns_an_error_if_semantic_search_is_used_without_a_query()
    {
        $tool = app(SearchLedgersTool::class);
        $request = new Request([
            'order_by' => 'semantic_score',
        ]);

        $response = $tool->handle($request);

        $this->assertTrue($response->isError());

        // Use reflection to access protected text property
        $content = $response->content();
        $reflection = new \ReflectionClass($content);
        $textProperty = $reflection->getProperty('text');
        $textProperty->setAccessible(true);
        $text = $textProperty->getValue($content);

        $this->assertStringContainsString('semantic_score sorting requires a search query', $text);
    }
}
