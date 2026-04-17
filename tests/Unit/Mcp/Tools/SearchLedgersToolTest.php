<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\AttachedFileStatus;
use App\Mcp\Tools\SearchLedgersTool;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\LedgerService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    #[Test]
    public function it_passes_array_filters_to_the_ledger_service_without_collapsing_them(): void
    {
        $params = [
            'q' => 'array lookup query',
            'folder_id' => [101, 202],
            'ledger_define_id' => [303, 404],
            'tags' => '重要,新規',
        ];

        $mockTrace = [
            'original_q' => 'array lookup query',
            'normalized_q' => 'array lookup query',
            'keywords' => ['array', 'lookup', 'query'],
            'tags' => [],
            'selected_terms' => [
                ['term' => 'array', 'kind' => 'original'],
            ],
            'excluded_terms' => [],
        ];

        $this->ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->withArgs(function ($user, $passedParams) use ($params) {
                return $user->id === $this->user->id
                    && $passedParams['q'] === $params['q']
                    && $passedParams['folder_id'] === $params['folder_id']
                    && $passedParams['ledger_define_id'] === $params['ledger_define_id']
                    && $passedParams['tags'] === $params['tags'];
            })
            ->andReturn(['ledgers' => collect([]), 'meta' => [], 'total' => 0, 'search_trace' => $mockTrace]);

        $response = $this->tool->handle(new Request($params));

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content()->__toString(), true);

        $this->assertSame($mockTrace, $responseData['search_trace']);
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
            new ColumnDefine(['id' => 0, 'name' => 'title', 'type' => 'text', 'order' => 0]),
            new ColumnDefine(['id' => 1, 'name' => 'description', 'type' => 'textarea', 'order' => 1]),
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
        $ledger->setAttribute('tenant_id', $this->getTenant()->id);
        // content_attachedを直接プロパティとして設定（AsColumnArrayJsonの動作を模擬）
        $ledger->content_attached = $contentAttachedObj;
        $firstAttachment = new AttachedFile([
            'id' => 11,
            'ledger_id' => $ledger->id,
            'column_id' => 1,
            'filename' => '請求書.pdf',
            'hashedbasename' => 'abc123hash',
            'mime' => 'application/pdf',
            'size' => 524288,
            'tenant_id' => $this->getTenant()->id,
            'status' => AttachedFileStatus::COMPLETED,
            'vlm_markdown' => '# 請求書',
            'vlm_structured_data' => [
                'pages' => [
                    ['page_index' => 1],
                ],
            ],
            'finalized_source' => 'vlm',
        ]);
        $firstAttachment->setAttribute('id', 11);

        $secondAttachment = new AttachedFile([
            'id' => 12,
            'ledger_id' => $ledger->id,
            'column_id' => 1,
            'filename' => '契約書.docx',
            'hashedbasename' => 'def456hash',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'size' => 1048576,
            'tenant_id' => $this->getTenant()->id,
            'finalized_source' => 'ocr',
        ]);
        $secondAttachment->setAttribute('id', 12);

        $ledger->setRelation('attachedFiles', new EloquentCollection([
            $firstAttachment,
            $secondAttachment,
        ]));

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
        $displayFields = $responseData['ledgers'][0]['__display_fields__'];

        $this->assertSame(2, $displayFields['attachment_count']);
        $this->assertSame('2件の添付', $displayFields['attachment_summary']);

        // 2つの添付ファイルがあることを確認
        $this->assertCount(2, $attachments);

        // 最初の添付ファイルの内容を確認
        $this->assertEquals(11, $attachments[0]['attachment_id']);
        $this->assertEquals('請求書.pdf', $attachments[0]['filename']);
        $this->assertEquals('請求書.pdf', $attachments[0]['name']);
        $this->assertEquals('primary', $attachments[0]['role']);
        $this->assertEquals(1, $attachments[0]['order']);
        $this->assertEquals('vlm', $attachments[0]['source']);
        $this->assertEquals('application/pdf', $attachments[0]['mime_type']);
        $this->assertEquals('text', $attachments[0]['delivery_mode']);
        $this->assertSame(['text', 'markdown', 'structured', 'json', 'visual'], $attachments[0]['available_formats']);
        $this->assertSame('ledgerleap://ledger/'.$this->getTenant()->id.'/1/attachments/11', $attachments[0]['resource_uri']);
        $this->assertSame('ledgerleap://ledger/{tenant}/{ledger}/attachments/{attachment}', $attachments[0]['resource_template']);
        $this->assertSame('mcp_resource', $attachments[0]['access_guide']['resource_type']);
        $this->assertSame('resources/read', $attachments[0]['access_guide']['read_via']);
        $this->assertSame($attachments[0]['resource_uri'], $attachments[0]['access_guide']['uri']);
        $this->assertArrayHasKey('routes', $attachments[0]);
        $this->assertSame(route('file.download', [
            'tenant' => $this->getTenant()->id,
            'attachedFile' => 11,
            'original' => true,
        ]), $attachments[0]['routes']['download']['url']);
        $this->assertSame(route('ledger.show', [
            'tenant' => $this->getTenant()->id,
            'ledgerId' => $ledger->id,
            'file' => 11,
        ]), $attachments[0]['routes']['inspector']['url']);
        $this->assertEquals(524288, $attachments[0]['size']);
        $this->assertEquals('512 KB', $attachments[0]['size_formatted']);
        $this->assertEquals('application/pdf', $attachments[0]['mime']);
        $this->assertEquals(1, $attachments[0]['column_id']);
        $this->assertEquals('abc123hash', $attachments[0]['hash']);
        $this->assertTrue($attachments[0]['payloads']['text']['available']);
        $this->assertStringContainsString('# 請求書', $attachments[0]['payloads']['text']['text']);
        $this->assertSame(1, $attachments[0]['payloads']['text']['lines'][0]['line_number']);
        $this->assertSame('# 請求書', $attachments[0]['payloads']['text']['lines'][0]['text']);
        $this->assertFalse($attachments[0]['payloads']['text']['truncated']);
        $this->assertTrue($attachments[0]['payloads']['structured']['available']);
        $this->assertSame([['page_index' => 1]], $attachments[0]['payloads']['structured']['pages']);
        $this->assertSame([], $attachments[0]['payloads']['structured']['text_blocks']);
        $this->assertSame([], $attachments[0]['payloads']['structured']['key_value_pairs']);
        $this->assertSame([
            'page_index' => true,
            'bbox' => false,
            'source_span' => false,
            'confidence' => false,
        ], $attachments[0]['payloads']['structured']['optional_fields']);
        $this->assertTrue($attachments[0]['payloads']['visual']['available']);
        $this->assertSame(route('file.download', [
            'tenant' => $this->getTenant()->id,
            'attachedFile' => 11,
        ]), $attachments[0]['payloads']['visual']['signed_url']);
        $this->assertTrue($attachments[0]['payloads']['visual']['auth_required']);

        // 2番目の添付ファイルの内容を確認
        $this->assertEquals(12, $attachments[1]['attachment_id']);
        $this->assertEquals('契約書.docx', $attachments[1]['filename']);
        $this->assertEquals('契約書.docx', $attachments[1]['name']);
        $this->assertEquals('supporting', $attachments[1]['role']);
        $this->assertEquals(2, $attachments[1]['order']);
        $this->assertEquals('ocr', $attachments[1]['source']);
        $this->assertEquals('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $attachments[1]['mime_type']);
        $this->assertEquals('text', $attachments[1]['delivery_mode']);
        $this->assertSame(['text'], $attachments[1]['available_formats']);
        $this->assertSame(route('file.download', [
            'tenant' => $this->getTenant()->id,
            'attachedFile' => 12,
            'original' => true,
        ]), $attachments[1]['routes']['download']['url']);
        $this->assertSame(route('ledger.show', [
            'tenant' => $this->getTenant()->id,
            'ledgerId' => $ledger->id,
            'file' => 12,
        ]), $attachments[1]['routes']['inspector']['url']);
        $this->assertEquals(1048576, $attachments[1]['size']);
        $this->assertEquals('1 MB', $attachments[1]['size_formatted']);
        $this->assertStringContainsString('wordprocessing', $attachments[1]['mime']);
        $this->assertEquals(1, $attachments[1]['column_id']);
        $this->assertEquals('def456hash', $attachments[1]['hash']);
        $this->assertTrue($attachments[1]['payloads']['text']['available']);
        $this->assertNull($attachments[1]['payloads']['text']['text']);
        $this->assertFalse($attachments[1]['payloads']['structured']['available']);
        $this->assertFalse($attachments[1]['payloads']['visual']['available']);
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

        // attachmentsキーは常に返し、空配列であることを確認
        $this->assertArrayHasKey('attachments', $responseData['ledgers'][0]);
        $this->assertSame([], $responseData['ledgers'][0]['attachments']);
        $this->assertSame(0, $responseData['ledgers'][0]['__display_fields__']['attachment_count']);
        $this->assertSame('添付なし', $responseData['ledgers'][0]['__display_fields__']['attachment_summary']);
    }

    #[Test]
    public function it_falls_back_to_content_attached_values_when_attachment_relation_is_missing()
    {
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);

        $contentAttachedObj = new \stdClass;
        $contentAttachedObj->{'1'} = [
            'hash-only' => [
                'name' => '未確定ファイル.txt',
                'size' => 128,
                'mime' => 'text/plain',
                'meta' => [
                    'content' => "1行目\n2行目",
                ],
            ],
        ];

        $ledger = new Ledger([
            'id' => 2,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'draft',
            'creator_id' => $creator->id,
            'content' => [0 => 'テストコンテンツ'],
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
        $this->assertCount(1, $attachments);
        $this->assertNull($attachments[0]['attachment_id']);
        $this->assertSame('未確定ファイル.txt', $attachments[0]['filename']);
        $this->assertSame('primary', $attachments[0]['role']);
        $this->assertSame(1, $attachments[0]['order']);
        $this->assertSame('unknown', $attachments[0]['source']);
        $this->assertSame('text/plain', $attachments[0]['mime_type']);
        $this->assertSame('text', $attachments[0]['delivery_mode']);
        $this->assertSame(['text'], $attachments[0]['available_formats']);
        $this->assertSame("1行目\n2行目", $attachments[0]['payloads']['text']['text']);
        $this->assertSame([
            ['line_number' => 1, 'text' => '1行目'],
            ['line_number' => 2, 'text' => '2行目'],
        ], $attachments[0]['payloads']['text']['lines']);
        $this->assertFalse($attachments[0]['payloads']['text']['truncated']);
        $this->assertSame([
            'available' => false,
            'url' => null,
        ], $attachments[0]['routes']['download']);
        $this->assertSame([
            'available' => false,
            'url' => null,
        ], $attachments[0]['routes']['inspector']);
        $this->assertNull($attachments[0]['access_guide']);
        $this->assertFalse($attachments[0]['payloads']['structured']['available']);
        $this->assertFalse($attachments[0]['payloads']['visual']['available']);
    }

    #[Test]
    public function it_marks_json_attachments_as_structured_delivery()
    {
        $creator = new User(['id' => $this->user->id, 'name' => 'テストユーザー']);
        $folder = new Folder(['id' => 1, 'name' => 'テストフォルダ', 'path' => '/テストフォルダ']);
        $ledgerDefine = new LedgerDefine([
            'id' => 1,
            'title' => 'テスト台帳',
            'folder_id' => $folder->id,
        ]);

        $contentAttachedObj = new \stdClass;
        $contentAttachedObj->{'2'} = [
            'structured-json' => [
                'name' => 'payload.json',
                'size' => 4096,
                'mime' => 'application/json',
                'pages' => [
                    ['page_index' => 1],
                ],
                'text_blocks' => [
                    [
                        'page_index' => 1,
                        'bbox' => [0, 0, 20, 10],
                        'source_span' => ['start' => 0, 'end' => 8],
                        'confidence' => 0.91,
                        'text' => '請求番号',
                    ],
                ],
                'key_value_pairs' => [
                    [
                        'key' => '請求番号',
                        'value' => 'INV-001',
                        'page_index' => 1,
                    ],
                ],
            ],
        ];

        $ledger = new Ledger([
            'id' => 3,
            'ledger_define_id' => $ledgerDefine->id,
            'status' => 'draft',
            'creator_id' => $creator->id,
            'content' => [0 => 'テストコンテンツ'],
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
        $this->assertCount(1, $attachments);
        $this->assertSame('payload.json', $attachments[0]['filename']);
        $this->assertSame('application/json', $attachments[0]['mime_type']);
        $this->assertSame('text', $attachments[0]['delivery_mode']);
        $this->assertSame(['text', 'structured', 'json'], $attachments[0]['available_formats']);
        $this->assertNull($attachments[0]['resource_template']);
        $this->assertNull($attachments[0]['resource_uri']);
        $this->assertNull($attachments[0]['access_guide']);
        $this->assertSame('primary', $attachments[0]['role']);
        $this->assertSame(1, $attachments[0]['order']);
        $this->assertTrue($attachments[0]['payloads']['text']['available']);
        $this->assertNull($attachments[0]['payloads']['text']['text']);
        $this->assertTrue($attachments[0]['payloads']['structured']['available']);
        $this->assertSame([['page_index' => 1]], $attachments[0]['payloads']['structured']['pages']);
        $this->assertSame([[
            'page_index' => 1,
            'bbox' => [0, 0, 20, 10],
            'source_span' => ['start' => 0, 'end' => 8],
            'confidence' => 0.91,
            'text' => '請求番号',
        ]], $attachments[0]['payloads']['structured']['text_blocks']);
        $this->assertSame([[
            'key' => '請求番号',
            'value' => 'INV-001',
            'page_index' => 1,
        ]], $attachments[0]['payloads']['structured']['key_value_pairs']);
        $this->assertSame([
            'page_index' => true,
            'bbox' => true,
            'source_span' => true,
            'confidence' => true,
        ], $attachments[0]['payloads']['structured']['optional_fields']);
        $this->assertFalse($attachments[0]['payloads']['visual']['available']);
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
