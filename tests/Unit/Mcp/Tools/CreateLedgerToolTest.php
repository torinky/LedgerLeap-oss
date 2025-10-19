<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\CreateLedgerTool;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * CreateLedgerToolの詳細テスト
 *
 * 責任範囲:
 * - 台帳作成のビジネスロジック
 * - リクエストパラメータのバリデーション
 * - サービス層との連携
 * - エラーハンドリング
 *
 * 注意: 認証関連のテストはMcpToolsAuthenticationTest.phpで統合的にテストされます
 */
class CreateLedgerToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected CreateLedgerTool $tool;

    protected User $user;

    protected PersonalAccessToken $accessToken;

    protected LedgerService $ledgerService;

    protected WritableFolderRepository $folderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // ユーザーとトークンを作成
        $this->user = User::factory()->create();
        $newAccessToken = $this->user->createToken('test-token');
        $this->accessToken = $newAccessToken->accessToken;

        // サービスをモック
        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->folderRepository = Mockery::mock(WritableFolderRepository::class);

        // Userモデルのイベントリスナー用のメソッドをデフォルトでモック
        $this->folderRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
        $this->folderRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);

        $this->app->instance(LedgerService::class, $this->ledgerService);
        $this->app->instance(WritableFolderRepository::class, $this->folderRepository);

        // CreateLedgerTool をインスタンス化
        $this->tool = new CreateLedgerTool;

        // MCP_AUTH_TOKEN 環境変数を設定
        putenv('MCP_AUTH_TOKEN='.$newAccessToken->plainTextToken);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        putenv('MCP_AUTH_TOKEN'); // 環境変数をクリーンアップ
    }

    // 注意: 認証関連のテストはMcpToolsAuthenticationTest.phpで統合的にテストされているため、
    // このテストクラスでは認証が成功した後のビジネスロジックに焦点を当てる

    #[Test]
    public function it_returns_error_if_folder_not_found(): void
    {
        $request = new Request([
            'ledger_define_id' => 1,
            'folder_id' => 99999, // 存在しないフォルダID
            'content' => '{"title": "Test"}',
        ]);

        $response = $this->tool->handle($request, $this->ledgerService);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Folder not found: 99999', $response->content());
    }

    #[Test]
    public function it_creates_ledger_successfully_with_valid_permissions(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // 権限ありのモック設定
        $this->folderRepository->shouldReceive('getAccessibleFolderIds')
            ->with(Mockery::type(User::class), \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([$folder->id]);

        // LedgerServiceのモック - 正しいLedgerモデルを返す
        $mockLedger = new \App\Models\Ledger([
            'id' => 123,
            'content' => ['title' => 'Test Ledger', 'amount' => 1000],
            'created_at' => now(),
        ]);
        $mockLedger->exists = true; // モデルが存在することを示す

        $this->ledgerService->shouldReceive('createLedger')
            ->once()
            ->with([
                'ledger_define_id' => $ledgerDefine->id,
                'content' => ['title' => 'Test Ledger', 'amount' => 1000],
                'tags' => ['test', 'automation'],
            ])
            ->andReturn($mockLedger);

        $request = new Request([
            'ledger_define_id' => $ledgerDefine->id,
            'folder_id' => $folder->id,
            'content' => '{"title": "Test Ledger", "amount": 1000}',
            'tags' => ['test', 'automation'],
        ]);

        $response = $this->tool->handle($request, $this->ledgerService);

        $this->assertFalse($response->isError());
        $this->assertJson($response->content());

        $responseData = json_decode($response->content(), true);
        // LedgerResourceの構造に基づいてアサーション
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('content', $responseData);
    }

    #[Test]
    public function it_handles_empty_tags_array(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // 権限ありのモック設定
        $this->folderRepository->shouldReceive('getAccessibleFolderIds')
            ->with(Mockery::type(User::class), \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([$folder->id]);

        $mockLedger = new \App\Models\Ledger([
            'id' => 123,
            'content' => ['title' => 'Test'],
            'created_at' => now(),
        ]);
        $mockLedger->exists = true;

        $this->ledgerService->shouldReceive('createLedger')
            ->once()
            ->with([
                'ledger_define_id' => $ledgerDefine->id,
                'content' => ['title' => 'Test'],
                'tags' => [],
            ])
            ->andReturn($mockLedger);

        $request = new Request([
            'ledger_define_id' => $ledgerDefine->id,
            'folder_id' => $folder->id,
            'content' => '{"title": "Test"}',
            'tags' => [],
        ]);

        $response = $this->tool->handle($request, $this->ledgerService);

        $this->assertFalse($response->isError());
    }

    #[Test]
    public function it_handles_service_exceptions_gracefully(): void
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // 権限ありのモック設定
        $this->folderRepository->shouldReceive('getAccessibleFolderIds')
            ->with(Mockery::type(User::class), \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([$folder->id]);

        // LedgerServiceが例外を投げる
        $this->ledgerService->shouldReceive('createLedger')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $request = new Request([
            'ledger_define_id' => $ledgerDefine->id,
            'folder_id' => $folder->id,
            'content' => '{"title": "Test"}',
        ]);

        $response = $this->tool->handle($request, $this->ledgerService);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Failed to create ledger: Database connection failed', $response->content());
    }

    #[Test]
    public function it_handles_invalid_json_content(): void
    {
        $folder = Folder::factory()->create();

        // 権限ありのモック設定
        $this->folderRepository->shouldReceive('getAccessibleFolderIds')
            ->with(Mockery::type(User::class), \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([$folder->id]);

        // LedgerServiceが例外を投げる（json_decode失敗によるnull渡し）
        $this->ledgerService->shouldReceive('createLedger')
            ->once()
            ->andThrow(new \Exception('Invalid content data'));

        $request = new Request([
            'ledger_define_id' => 1,
            'folder_id' => $folder->id,
            'content' => 'invalid-json-format',
        ]);

        $response = $this->tool->handle($request, $this->ledgerService);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Failed to create ledger', $response->content());
    }

    #[Test]
    public function it_returns_error_if_user_lacks_write_permission()
    {
        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // 権限なしのモック設定
        $this->folderRepository->shouldReceive('getAccessibleFolderIds')
            ->with(Mockery::type(User::class), \App\Enums\FolderPermissionType::WRITE)
            ->andReturn([]); // 空の配列を返し、書き込み権限がないことを示す

        $this->ledgerService->shouldNotReceive('createLedger');

        $request = new Request([
            'ledger_define_id' => $ledgerDefine->id,
            'folder_id' => $folder->id,
            'content' => '{"title": "Test"}',
        ]);

        $response = $this->tool->handle($request, $this->ledgerService);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Permission denied to create ledger in folder: '.$folder->id, $response->content());
    }
}
