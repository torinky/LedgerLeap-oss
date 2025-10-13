<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Tools\SearchLedgersTool;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Mockery;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * MCP SearchLedgersTool のソート機能テスト
 * 
 * 注: スコアリング機能の詳細なテストは RecordsTableCompositeScoreSortTest で実施済み
 * このテストはMCPツール固有のAPI（order_by/order_directionパラメータ）の動作を確認
 */
class SearchLedgersToolSortingTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected SearchLedgersTool $tool;

    protected LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // ユーザーとトークンを作成
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // MCP_AUTH_TOKEN 環境変数を設定
        putenv('MCP_AUTH_TOKEN='.$token);

        // LedgerService をモック
        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->app->instance(LedgerService::class, $this->ledgerService);

        // ツールをインスタンス化
        $this->tool = new SearchLedgersTool($this->ledgerService);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN');
        Mockery::close();
        parent::tearDown();
    }

    public function test_accepts_order_by_parameter(): void
    {
        // order_byパラメータが正しく受け取られることを確認
        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::type(\App\Models\User::class),
                Mockery::on(function ($params) {
                    return $params['order_by'] === 'activity_score' 
                        && $params['format'] === 'raw';
                })
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $request = new Request([
            'order_by' => 'activity_score',
            'format' => 'raw',
        ]);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
    }

    public function test_accepts_order_direction_parameter(): void
    {
        // order_directionパラメータが正しく受け取られることを確認
        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::type(\App\Models\User::class),
                Mockery::on(function ($params) {
                    return $params['order_by'] === 'composite_score'
                        && $params['order_direction'] === 'asc';
                })
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $request = new Request([
            'order_by' => 'composite_score',
            'order_direction' => 'asc',
            'format' => 'raw',
        ]);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
    }

    public function test_defaults_to_composite_score_when_no_order_by_specified(): void
    {
        // order_byが指定されていない場合のデフォルト動作を確認
        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::type(\App\Models\User::class),
                Mockery::on(function ($params) {
                    // order_byが指定されていないか、composite_scoreがデフォルト
                    return !isset($params['order_by']) || $params['order_by'] === 'composite_score';
                })
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $request = new Request(['format' => 'raw']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
    }

    public function test_supports_all_sort_field_options(): void
    {
        // 全てのソートオプションが受け入れられることを確認
        $sortFields = ['composite_score', 'activity_score', 'created_at', 'updated_at'];

        foreach ($sortFields as $field) {
            $this->ledgerService->shouldReceive('searchLedgersForApi')
                ->once()
                ->with(
                    Mockery::type(\App\Models\User::class),
                    Mockery::on(function ($params) use ($field) {
                        return $params['order_by'] === $field;
                    })
                )
                ->andReturn([
                    'ledgers' => collect([]),
                    'meta' => [],
                    'total' => 0,
                ]);

            $request = new Request([
                'order_by' => $field,
                'format' => 'raw',
            ]);
            $response = $this->tool->handle($request);

            $this->assertFalse($response->isError(), "Sort field '{$field}' should be accepted");
        }
    }

    public function test_supports_both_sort_directions(): void
    {
        // 昇順・降順の両方が受け入れられることを確認
        foreach (['asc', 'desc'] as $direction) {
            $this->ledgerService->shouldReceive('searchLedgersForApi')
                ->once()
                ->with(
                    Mockery::type(\App\Models\User::class),
                    Mockery::on(function ($params) use ($direction) {
                        return $params['order_direction'] === $direction;
                    })
                )
                ->andReturn([
                    'ledgers' => collect([]),
                    'meta' => [],
                    'total' => 0,
                ]);

            $request = new Request([
                'order_by' => 'composite_score',
                'order_direction' => $direction,
                'format' => 'raw',
            ]);
            $response = $this->tool->handle($request);

            $this->assertFalse($response->isError(), "Sort direction '{$direction}' should be accepted");
        }
    }
}


