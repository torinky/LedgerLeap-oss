<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\SearchLedgersTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
        putenv('MCP_AUTH_TOKEN='.$newAccessToken->plainTextToken);
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
    public function it_returns_raw_format_correctly()
    {
        $params = ['format' => 'raw'];
        $mockLedger = Ledger::factory()->make();
        $mockMeta = ['ledger_defines' => [], 'folders' => [], 'users' => []];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$mockLedger]), 'meta' => $mockMeta, 'total' => 1]);

        $request = new Request($params);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertArrayHasKey('meta', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertArrayNotHasKey('__summary__', $responseData);
        $this->assertArrayNotHasKey('__display_fields__', $responseData['ledgers'][0]);
    }

    #[Test]
    public function it_returns_summary_format_with_correct_display_fields_and_translation_keys()
    {
        // Translatorを部分モック化
        // Translatorを部分モック化
        $translator = Mockery::mock(app(Translator::class))->makePartial();
        $this->instance(Translator::class, $translator);

        // テストデータ
        $creator = User::factory()->make(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = Folder::factory()->make(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = LedgerDefine::factory()->make(['id' => 1, 'name' => 'テスト台帳', 'folder_id' => $folder->id]);
        $ledger = Ledger::factory()->make([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'pending_approval',
            'creator_id' => $creator->id,
            'updated_at' => now()->subHour(),
        ]);

        $mockMeta = [
            'ledger_defines' => [$ledgerDefine->id => $ledgerDefine->toArray()],
            'folders' => [$folder->id => $folder->toArray()],
            'users' => [$creator->id => $creator->toArray()],
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$ledger]), 'meta' => $mockMeta, 'total' => 1]);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        // 構造の検証
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('__display_fields__', $responseData['ledgers'][0]);

        // 翻訳キーの呼び出しを検証
        $translator->shouldHaveReceived('get')->with('ledger.field.title', [], 'ja');
        $translator->shouldHaveReceived('get')->with('ledger.field.folder', [], 'ja');
        $translator->shouldHaveReceived('get')->with('ledger.field.creator', [], 'ja');
        $translator->shouldHaveReceived('get')->with('ledger.field.status', [], 'ja');
        $translator->shouldHaveReceived('get')->with('ledger.field.updated_at', [], 'ja');
        $translator->shouldHaveReceived('get')->with('ledger.workflow.status.pending_approval', [], 'ja');
        $translator->shouldHaveReceived('choice')->with('messages.found_ledgers', 1, [], 'ja');

        // __display_fields__ の内容を検証
        $displayFields = $responseData['ledgers'][0]['__display_fields__'];
        $this->assertEquals($ledgerDefine->name, $displayFields[__('ledger.field.title', [], 'ja')]);
        $this->assertEquals($folder->path, $displayFields[__('ledger.field.folder', [], 'ja')]);
        $this->assertEquals($creator->name, $displayFields[__('ledger.field.creator', [], 'ja')]);
        $this->assertEquals(__('ledger.workflow.status.pending_approval', [], 'ja'), $displayFields[__('ledger.field.status', [], 'ja')]);
    }

    #[Test]
    public function it_handles_empty_results_for_summary_format()
    {
        // Translatorをスパイ
        $translator = Mockery::spy(Translator::class);
        $this->instance('translator', $translator);

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([]), 'meta' => [], 'total' => 0]);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertCount(0, $responseData['ledgers']);

        $translator->shouldHaveReceived('choice')->with('messages.found_ledgers', 0, [], 'ja');
    }
}
