<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\FolderPermissionType;
use App\Mcp\Tools\ClaimWorkflowTaskTool;
use App\Mcp\Tools\CreateLedgerTool;
use App\Mcp\Tools\ExecuteApprovalTool;
use App\Mcp\Tools\GetActivityLogTool;
use App\Mcp\Tools\GetFolderStatsTool;
use App\Mcp\Tools\GetLedgerDefinesTool;
use App\Mcp\Tools\GetLedgerStatsTool;
use App\Mcp\Tools\GetPendingApprovalsTool;
use App\Mcp\Tools\GetUserActivityStatsTool;
use App\Mcp\Tools\GetWorkflowHistoryTool;
use App\Mcp\Tools\SearchLedgersTool;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * MCPツールの統一認証機能テスト
 *
 * 責任範囲:
 * - 全MCPツールの認証動作の一貫性検証
 * - AuthenticatedMcpTraitの統合動作確認
 * - トークン検証・権限チェックの基本動作
 *
 * 注意: 各ツール固有の詳細機能は個別のテストクラスで実施
 */
class McpToolsAuthenticationTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // ユーザー作成とトークン生成
        $this->user = User::factory()->create();
        $tokenResult = $this->user->createToken('test-token');
        $this->validToken = $tokenResult->plainTextToken;
    }

    #[Test]
    public function search_ledgers_tool_rejects_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $ledgerService = Mockery::mock(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);
        $request = new Request([]);

        $response = $tool->handle($request);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Authentication token not provided', $response->content());
    }

    #[Test]
    public function search_ledgers_tool_accepts_valid_token(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $ledgerService = Mockery::mock(LedgerService::class);
        $ledgerService->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(Mockery::type(User::class), Mockery::any())
            ->andReturn([
                'ledgers' => [],
                'total' => 0,
                'meta' => ['ledger_defines' => [], 'folders' => [], 'users' => []],
            ]);

        $tool = new SearchLedgersTool($ledgerService);
        $request = new Request([]);

        $response = $tool->handle($request);

        $this->assertFalse($response->isError());
    }

    #[Test]
    public function create_ledger_tool_rejects_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $ledgerService = Mockery::mock(LedgerService::class);
        $tool = new CreateLedgerTool;
        $request = new Request([
            'ledger_define_id' => 1,
            'folder_id' => 1,
            'content' => '{"test": "data"}',
        ]);

        $response = $tool->handle($request, $ledgerService);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Authentication token not provided', $response->content());
    }

    #[Test]
    public function create_ledger_tool_checks_folder_permissions(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        $ledgerService = Mockery::mock(LedgerService::class);

        // WritableFolderRepositoryをモック（権限なし）
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->with(Mockery::type(User::class), FolderPermissionType::WRITE)
            ->andReturn([]); // 空の配列 = 権限なし

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new CreateLedgerTool;
        $request = new Request([
            'ledger_define_id' => 1,
            'folder_id' => $folder->id,
            'content' => '{"test": "data"}',
        ]);

        $response = $tool->handle($request, $ledgerService);

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Insufficient permission', $response->content());
    }

    #[Test]
    public function get_ledger_defines_tool_filters_by_user_permissions(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder1 = Folder::factory()->create();
        $folder2 = Folder::factory()->create();
        $ledgerDefine1 = LedgerDefine::factory()->create(['folder_id' => $folder1->id]);
        $ledgerDefine2 = LedgerDefine::factory()->create(['folder_id' => $folder2->id]);

        // ユーザーはfolder1のみアクセス可能
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder1->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        // レスポンスにfolder1の台帳定義のみが含まれることを確認
        $responseData = json_decode($response->content(), true);
        $this->assertCount(1, $responseData);
        $this->assertEquals($ledgerDefine1->id, $responseData[0]['id']);
    }

    #[Test]
    public function invalid_token_is_rejected_by_all_tools(): void
    {
        putenv('MCP_AUTH_TOKEN=invalid-token-12345');

        $tools = [
            new SearchLedgersTool(Mockery::mock(LedgerService::class)),
            new CreateLedgerTool,
            new GetLedgerDefinesTool,
            new GetPendingApprovalsTool,
            new ExecuteApprovalTool,
            new GetWorkflowHistoryTool,
            new ClaimWorkflowTaskTool,
            new GetActivityLogTool,
            new GetLedgerStatsTool(Mockery::mock(\App\Services\AnalyticsService::class)),
            new GetUserActivityStatsTool(Mockery::mock(\App\Services\AnalyticsService::class)),
            new GetFolderStatsTool(Mockery::mock(\App\Services\AnalyticsService::class)),
        ];

        foreach ($tools as $tool) {
            $request = new Request([]);

            if ($tool instanceof CreateLedgerTool) {
                $response = $tool->handle($request, Mockery::mock(LedgerService::class));
            } elseif ($tool instanceof GetLedgerDefinesTool) {
                $response = $tool->handle($request, Mockery::mock(WritableFolderRepository::class));
            } elseif ($tool instanceof GetPendingApprovalsTool) {
                $response = $tool->handle($request, Mockery::mock(\App\Services\WorkflowService::class));
            } elseif ($tool instanceof ExecuteApprovalTool) {
                $response = $tool->handle($request, Mockery::mock(\App\Services\WorkflowService::class));
            } elseif ($tool instanceof ClaimWorkflowTaskTool) {
                $response = $tool->handle($request, Mockery::mock(\App\Services\WorkflowService::class));
            } else {
                $response = $tool->handle($request);
            }

            $this->assertTrue($response->isError());
            $this->assertStringContainsString('Invalid authentication token', $response->content());
        }
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();
        parent::tearDown();
    }
}
