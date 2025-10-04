<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\SearchLedgersTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SearchLedgersToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected LedgerService $ledgerService;

    protected SearchLedgersTool $tool;

    protected User $user;

    protected PersonalAccessToken $accessToken;

    protected function setUp(): void
    {
        parent::setUp();

        // テナントは既に初期化されている（RefreshDatabaseWithTenantが処理済み）
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

    public function it_returns_summary_format_with_correct_display_fields_and_translation_keys()
    {
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

        // __display_fields__ のキーが英語であることを検証
        $displayFields = $responseData['ledgers'][0]['__display_fields__'];
        $this->assertArrayHasKey('title', $displayFields);
        $this->assertArrayHasKey('folder', $displayFields);
        $this->assertArrayHasKey('creator', $displayFields);
        $this->assertArrayHasKey('workflow_status', $displayFields);
        $this->assertArrayHasKey('updated_at', $displayFields);

        // 値の検証（翻訳済み）
        $this->assertEquals($ledgerDefine->name, $displayFields['title']);
        $this->assertEquals($folder->path, $displayFields['folder']);
        $this->assertEquals($creator->name, $displayFields['creator']);
        $this->assertEquals('承認待ち', $displayFields['workflow_status']); // 翻訳済みの値
    }

    #[Test]
    public function it_handles_empty_results_for_summary_format()
    {
        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([]), 'meta' => [], 'total' => 0]);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertCount(0, $responseData['ledgers']);
        $this->assertEquals(0, $responseData['total']);

        // __summary__の値が適切な形式であることを確認
        $this->assertIsString($responseData['__summary__']);
    }

    #[Test]
    public function it_returns_summary_format_without_content()
    {
        $creator = User::factory()->make(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = Folder::factory()->make(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = LedgerDefine::factory()->make([
            'id' => 1,
            'name' => 'テスト台帳',
            'folder_id' => $folder->id,
            'column_define' => [
                ['id' => 0, 'name' => 'title', 'type' => 'text', 'order' => 0],
                ['id' => 1, 'name' => 'description', 'type' => 'textarea', 'order' => 1],
            ],
        ]);
        $ledger = Ledger::factory()->make([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'pending_approval',
            'creator_id' => $creator->id,
            'content' => [0 => 'テストタイトル', 1 => 'これはテスト説明です'],
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

        $request = new Request(['format' => 'summary', 'include_content' => false]);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        // content_preview が含まれることを確認
        $displayFields = $responseData['ledgers'][0]['__display_fields__'];
        $this->assertArrayHasKey('content_preview', $displayFields);
        $this->assertIsString($displayFields['content_preview']);
        $this->assertStringContainsString('title:', $displayFields['content_preview']);
    }

    #[Test]
    public function it_uses_english_keys_in_display_fields()
    {
        $creator = User::factory()->make(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = Folder::factory()->make(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = LedgerDefine::factory()->make(['id' => 1, 'name' => 'テスト台帳', 'folder_id' => $folder->id]);
        $ledger = Ledger::factory()->make([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'approved',
            'creator_id' => $creator->id,
            'updated_at' => now(),
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

        $displayFields = $responseData['ledgers'][0]['__display_fields__'];

        // キーが英語であることを確認
        $this->assertArrayHasKey('title', $displayFields);
        $this->assertArrayHasKey('folder', $displayFields);
        $this->assertArrayHasKey('creator', $displayFields);
        $this->assertArrayHasKey('workflow_status', $displayFields);
        $this->assertArrayHasKey('updated_at', $displayFields);

        // 日本語のキーが存在しないことを確認
        $this->assertArrayNotHasKey('台帳', $displayFields);
        $this->assertArrayNotHasKey('フォルダ', $displayFields);
        $this->assertArrayNotHasKey('作成者', $displayFields);
        $this->assertArrayNotHasKey('ステータス', $displayFields);
    }
}
