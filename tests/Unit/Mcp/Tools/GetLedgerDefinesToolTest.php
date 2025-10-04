<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetLedgerDefinesTool;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * GetLedgerDefinesToolの詳細テスト
 *
 * 責任範囲:
 * - 台帳定義のフィルタリング機能
 * - 複数フォルダアクセス権限の処理
 * - レスポンス形式の検証
 * - エッジケースの処理
 *
 * 注意: 認証関連のテストはMcpToolsAuthenticationTest.phpで統合的にテストされます
 */
class GetLedgerDefinesToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();

        // テナント作成・初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        // ユーザー作成とトークン生成
        $this->user = User::factory()->create();
        $tokenResult = $this->user->createToken('test-token');
        $this->validToken = $tokenResult->plainTextToken;
    }

    // 注意: 認証関連のテストはMcpToolsAuthenticationTest.phpで統合的にテストされているため、
    // このテストクラスでは認証が成功した後のデータフィルタリング機能に焦点を当てる

    #[Test]
    public function it_returns_ledger_defines_user_has_access_to(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $accessibleFolder = Folder::factory()->create(['title' => 'Accessible Folder']);
        $inaccessibleFolder = Folder::factory()->create(['title' => 'Inaccessible Folder']);

        $accessibleLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $accessibleFolder->id,
            'title' => 'Accessible Define',
        ]);
        $inaccessibleLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $inaccessibleFolder->id,
            'title' => 'Inaccessible Define',
        ]);

        // ユーザーはaccessibleFolderのみアクセス可能
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$accessibleFolder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $this->assertCount(1, $responseData);
        $this->assertEquals($accessibleLedgerDefine->id, $responseData[0]['id']);
        $this->assertEquals('Accessible Define', $responseData[0]['name']); // ResourceではnameフィールドとしてtitleValue返される
    }

    #[Test]
    public function it_returns_empty_array_when_user_has_no_accessible_folders(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // ユーザーはどのフォルダにもアクセスできない
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $this->assertCount(0, $responseData);
    }

    #[Test]
    public function it_formats_response_using_ledger_define_resource(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Test Define',
            'create_description' => 'Test Description',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        // レスポンスがLedgerDefineResourceの形式であることを確認
        $responseData = json_decode($response->content(), true);
        $this->assertCount(1, $responseData);

        $ledgerDefineData = $responseData[0];
        $this->assertArrayHasKey('id', $ledgerDefineData);
        $this->assertArrayHasKey('name', $ledgerDefineData); // Resourceでは'name'フィールド
        $this->assertEquals($ledgerDefine->id, $ledgerDefineData['id']);
        $this->assertEquals('Test Define', $ledgerDefineData['name']);
    }

    #[Test]
    public function it_handles_multiple_accessible_folders(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder1 = Folder::factory()->create(['title' => 'Folder 1']);
        $folder2 = Folder::factory()->create(['title' => 'Folder 2']);
        $folder3 = Folder::factory()->create(['title' => 'Folder 3']);

        $ledgerDefine1 = LedgerDefine::factory()->create([
            'folder_id' => $folder1->id,
            'title' => 'Define 1',
        ]);
        $ledgerDefine2 = LedgerDefine::factory()->create([
            'folder_id' => $folder2->id,
            'title' => 'Define 2',
        ]);
        $ledgerDefine3 = LedgerDefine::factory()->create([
            'folder_id' => $folder3->id,
            'title' => 'Define 3',
        ]);

        // ユーザーはfolder1とfolder3にアクセス可能
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder1->id, $folder3->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $this->assertCount(2, $responseData);

        $returnedIds = array_column($responseData, 'id');
        $this->assertContains($ledgerDefine1->id, $returnedIds);
        $this->assertContains($ledgerDefine3->id, $returnedIds);
        $this->assertNotContains($ledgerDefine2->id, $returnedIds);
    }

    #[Test]
    public function it_returns_pretty_printed_json(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        // JSONが整形されていることを確認（改行とインデントが含まれる）
        $content = $response->content();
        $this->assertStringContainsString("\n", $content);
        $this->assertStringContainsString('    ', $content); // インデント
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();
        parent::tearDown();
    }
}
