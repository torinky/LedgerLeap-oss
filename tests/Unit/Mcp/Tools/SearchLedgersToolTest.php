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
        $this->setUpRefreshDatabaseWithTenant();

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
        $this->assertStringContainsString('MCP_AUTH_TOKEN environment variable is not set', $response->content());
    }

    #[Test]
    public function it_returns_unauthorized_if_token_is_invalid()
    {
        putenv('MCP_AUTH_TOKEN=invalid-token'); // 無効なトークンを設定
        $request = new Request([]);
        $response = $this->tool->handle($request);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('The provided token is invalid or has been revoked', $response->content());
    }

    #[Test]
    public function it_returns_raw_format_correctly()
    {
        $params = ['format' => 'raw'];
        // make()を使う場合、tenant_idは不要（DBに保存しないため）
        // factoryのデフォルト値生成をスキップするために、必要な属性のみ指定
        $mockLedger = new Ledger([
            'id' => 1,
            'status' => 'draft',
        ]);
        $mockMeta = ['ledger_defines' => [], 'folders' => [], 'users' => []];
        $mockTrace = [
            'original_q' => 'test',
            'normalized_q' => 'test',
            'keywords' => ['test'],
            'tags' => [],
            'selected_terms' => [['term' => 'test', 'kind' => 'original']],
            'excluded_terms' => [],
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$mockLedger]), 'meta' => $mockMeta, 'total' => 1, 'search_trace' => $mockTrace]);

        $request = new Request($params);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertArrayHasKey('meta', $responseData);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertArrayHasKey('search_trace', $responseData);
        $this->assertArrayNotHasKey('__summary__', $responseData);
        $this->assertArrayNotHasKey('__display_fields__', $responseData['ledgers'][0]);
    }

    public function it_returns_summary_format_with_correct_display_fields_and_translation_keys()
    {
        // テストデータ
        $creator = User::factory()->make(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = Folder::factory()->make(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = LedgerDefine::factory()->make(['id' => 1, 'name' => 'テスト台帳', 'folder_id' => $folder->id, 'tenant_id' => $this->getTenant()->id]);
        $ledger = Ledger::factory()->make([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'pending_approval',
            'creator_id' => $creator->id,
            'tenant_id' => $this->getTenant()->id,
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
        // テストデータを配列で直接作成（factory()->make()のテナント問題を回避）
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);

        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);
        $ledgerDefine->column_define = [
            new \App\Models\ColumnDefine(['id' => 0, 'name' => 'title', 'type' => 'text', 'order' => 0]),
            new \App\Models\ColumnDefine(['id' => 1, 'name' => 'description', 'type' => 'textarea', 'order' => 1]),
        ];

        $ledger = new Ledger([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'pending_approval',
            'creator_id' => $creator->id,
            'content' => [0 => 'テストタイトル', 1 => 'これはテスト説明です'],
            'updated_at' => now()->subHour(),
        ]);

        $mockMeta = [
            'ledger_defines' => [$ledgerDefine->id => ['id' => 1, 'title' => 'テスト台帳', 'folder_id' => $folder->id, 'column_define' => [['id' => 0, 'name' => 'title'], ['id' => 1, 'name' => 'description']]]],
            'folders' => [$folder->id => ['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']],
            'users' => [$creator->id => ['id' => $this->user->id, 'name' => 'テストユーザー']],
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
        // テストデータを配列で直接作成（factory()->make()のテナント問題を回避）
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);

        $ledger = new Ledger([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'approved',
            'creator_id' => $creator->id,
            'updated_at' => now(),
        ]);

        $mockMeta = [
            'ledger_defines' => [$ledgerDefine->id => ['id' => 1, 'title' => 'テスト台帳', 'folder_id' => $folder->id]],
            'folders' => [$folder->id => ['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']],
            'users' => [$creator->id => ['id' => $this->user->id, 'name' => 'テストユーザー']],
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

    #[Test]
    public function it_includes_attachment_info_in_summary_format()
    {
        // テストデータを作成
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);

        // 添付ファイル情報を含む台帳
        // 実際のAsColumnArrayJsonキャストはobjectを返すため、ここでもobjectで作成
        $contentAttachedObj = new \stdClass;
        $contentAttachedObj->{'1'} = [
            'abc123hash' => [
                'name' => '請求書.pdf',
                'size' => 524288,  // 512 KB
                'mime' => 'application/pdf',
                'path' => 'attachments/1/abc123hash.pdf',
            ],
            'def456hash' => [
                'name' => '契約書.docx',
                'size' => 1048576,  // 1 MB
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'path' => 'attachments/1/def456hash.docx',
            ],
        ];

        $ledger = new Ledger([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'draft',
            'creator_id' => $creator->id,
            'content' => [0 => 'テストコンテンツ'],
            'updated_at' => now(),
        ]);
        // content_attachedを直接プロパティとして設定（AsColumnArrayJsonの動作を模擬）
        $ledger->content_attached = $contentAttachedObj;

        $mockMeta = [
            'ledger_defines' => [$ledgerDefine->id => ['id' => 1, 'title' => 'テスト台帳', 'folder_id' => $folder->id]],
            'folders' => [$folder->id => ['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']],
            'users' => [$creator->id => ['id' => $this->user->id, 'name' => 'テストユーザー']],
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$ledger]), 'meta' => $mockMeta, 'total' => 1]);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        // attachments キーが含まれることを確認
        $this->assertArrayHasKey('attachments', $responseData['ledgers'][0]);
        $attachments = $responseData['ledgers'][0]['attachments'];

        // 2つの添付ファイルがあることを確認
        $this->assertCount(2, $attachments);

        // 最初の添付ファイルの内容を確認
        $this->assertEquals('請求書.pdf', $attachments[0]['name']);
        $this->assertEquals(524288, $attachments[0]['size']);
        $this->assertEquals('512 KB', $attachments[0]['size_formatted']);
        $this->assertEquals('application/pdf', $attachments[0]['mime']);
        $this->assertEquals(1, $attachments[0]['column_id']);
        $this->assertEquals('abc123hash', $attachments[0]['hash']);

        // 2番目の添付ファイルの内容を確認
        $this->assertEquals('契約書.docx', $attachments[1]['name']);
        $this->assertEquals(1048576, $attachments[1]['size']);
        $this->assertEquals('1 MB', $attachments[1]['size_formatted']);
        $this->assertStringContainsString('wordprocessing', $attachments[1]['mime']);
        $this->assertEquals(1, $attachments[1]['column_id']);
        $this->assertEquals('def456hash', $attachments[1]['hash']);
    }

    #[Test]
    public function it_handles_ledgers_without_attachments()
    {
        // 添付ファイルなしの台帳
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);

        $ledger = new Ledger([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'draft',
            'creator_id' => $creator->id,
            'content' => [0 => 'テストコンテンツ'],
            'content_attached' => [],  // 空の配列
            'updated_at' => now(),
        ]);

        $mockMeta = [
            'ledger_defines' => [$ledgerDefine->id => ['id' => 1, 'title' => 'テスト台帳', 'folder_id' => $folder->id]],
            'folders' => [$folder->id => ['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']],
            'users' => [$creator->id => ['id' => $this->user->id, 'name' => 'テストユーザー']],
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$ledger]), 'meta' => $mockMeta, 'total' => 1]);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        // attachmentsキーが存在しないことを確認
        $this->assertArrayNotHasKey('attachments', $responseData['ledgers'][0]);
    }

    #[Test]
    public function it_formats_file_sizes_correctly()
    {
        // 様々なサイズの添付ファイルをテスト
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);

        // AsColumnArrayJsonの動作を模擬（objectとして作成）
        $contentAttachedObj = new \stdClass;
        $contentAttachedObj->{'1'} = [
            'small' => ['name' => 'small.txt', 'size' => 512, 'mime' => 'text/plain'],
            'medium' => ['name' => 'medium.pdf', 'size' => 2097152, 'mime' => 'application/pdf'],  // 2 MB
            'large' => ['name' => 'large.zip', 'size' => 5368709120, 'mime' => 'application/zip'],  // 5 GB
        ];

        $ledger = new Ledger([
            'id' => 1,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'draft',
            'creator_id' => $creator->id,
            'updated_at' => now(),
        ]);
        $ledger->content_attached = $contentAttachedObj;

        $mockMeta = [
            'ledger_defines' => [$ledgerDefine->id => ['id' => 1, 'title' => 'テスト台帳', 'folder_id' => $folder->id]],
            'folders' => [$folder->id => ['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']],
            'users' => [$creator->id => ['id' => $this->user->id, 'name' => 'テストユーザー']],
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->andReturn(['ledgers' => collect([$ledger]), 'meta' => $mockMeta, 'total' => 1]);

        $request = new Request(['format' => 'summary']);
        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        $attachments = $responseData['ledgers'][0]['attachments'];
        $this->assertCount(3, $attachments);

        // サイズフォーマットの確認
        $this->assertEquals('512 B', $attachments[0]['size_formatted']);
        $this->assertEquals('2 MB', $attachments[1]['size_formatted']);
        $this->assertEquals('5 GB', $attachments[2]['size_formatted']);
    }
}
