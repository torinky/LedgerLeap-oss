<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\SearchLedgersTool;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SearchLedgersToolTest extends TestCase
{
    use RefreshDatabase;

    protected LedgerService $ledgerService;
    protected SearchLedgersTool $tool;
    protected User $user;
    protected PersonalAccessToken $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントを作成し、初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        // ユーザーとトークンを作成
        $this->user = User::factory()->create();
        $newAccessToken = $this->user->createToken('test-token');
        $this->accessToken = $newAccessToken->accessToken;

        // LedgerService をモック
        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->app->instance(LedgerService::class, $this->ledgerService);

        // SearchLedgersTool をインスタンス化
        $this->tool = new SearchLedgersTool($this->ledgerService);

        // MCP_AUTH_TOKEN 環境変数を設定
        putenv('MCP_AUTH_TOKEN=' . $newAccessToken->plainTextToken);

        // Laravel\Mcp\Responseの構造を確認するためのdd
        // dd($this->tool->handle(new Request([])));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        putenv('MCP_AUTH_TOKEN'); // 環境変数をクリーンアップ
    }

    #[Test]
    public function it_returns_unauthorized_if_token_is_missing()
    {
        putenv('MCP_AUTH_TOKEN'); // トークンを削除
        $request = new Request([]);
        $response = $this->tool->handle($request);

        $this->assertTrue($response->isError());
        $this->assertEquals('Authentication token not provided.', $response->content());
    }

    #[Test]
    public function it_returns_unauthorized_if_token_is_invalid()
    {
        putenv('MCP_AUTH_TOKEN=invalid-token'); // 無効なトークンを設定
        $request = new Request([]);
        $response = $this->tool->handle($request);

        $this->assertTrue($response->isError());
        $this->assertEquals('Invalid authentication token.', $response->content());
    }

    #[Test]
    public function it_calls_ledger_service_with_correct_parameters_for_raw_format()
    {
        $params = [
            'q' => 'test',
            'creator_id' => $this->user->id,
            'created_from' => '2023-01-01',
            'created_to' => '2023-01-31',
            'limit' => 5,
            'offset' => 0,
            'format' => 'raw',
        ];
        $expectedServiceParams = [
            'q' => 'test',
            'creator_id' => $this->user->id,
            'limit' => 5,
            'offset' => 0,
            'format' => 'raw',
            'created_between' => '2023-01-01,2023-01-31',
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->withArgs(function ($user, $receivedParams) use ($expectedServiceParams) {
                return $user->is($this->user) && $receivedParams === $expectedServiceParams;
            })
            ->andReturn(['ledgers' => [], 'total' => 0]);

        $request = new Request($params);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $this->assertJson($response->content()->__toString());
        $responseData = json_decode($response->content()->__toString(), true);
        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertArrayHasKey('total', $responseData);
    }

    #[Test]
    public function it_returns_summary_format_with_display_fields_and_summary_text()
    {
        // テスト用の台帳データを作成
        $ledgerDefine = LedgerDefine::factory()->create(['title' => '日報']);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'pending_approval',
            'updated_at' => now()->subHour(),
            'creator_id' => $this->user->id,
        ]);
        $ledger->load('define'); // リレーションをロード

        $params = [
            'q' => '日報',
            'creator_id' => $this->user->id,
            'format' => 'summary',
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$ledger]), 'total' => 1]);

        $request = new Request($params);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $this->assertJson($response->content()->__toString());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertStringContainsString('あなたが作成した台帳は1件です。', $responseData['__summary__']);

        $this->assertCount(1, $responseData['ledgers']);
        $firstLedger = $responseData['ledgers'][0];
        $this->assertArrayHasKey('__display_fields__', $firstLedger);
        $this->assertEquals('日報', $firstLedger['__display_fields__']['件名']);
        $this->assertEquals('承認待ち', $firstLedger['__display_fields__']['ステータス']);
        $this->assertStringContainsString(now()->subHour()->format('Y年m月d日 H:i'), $firstLedger['__display_fields__']['更新日時']);
    }

    #[Test]
    public function it_handles_empty_results_for_summary_format()
    {
        $params = [
            'q' => '存在しないキーワード',
            'format' => 'summary',
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([]), 'total' => 0]);

        $request = new Request($params);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $this->assertJson($response->content()->__toString());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertStringContainsString('台帳が0件見つかりました。', $responseData['__summary__']);
        $this->assertCount(0, $responseData['ledgers']);
    }
}
