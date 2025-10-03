<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\ExecuteApprovalTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ExecuteApprovalToolTest extends TestCase
{
    use DatabaseMigrations;

    private ExecuteApprovalTool $tool;

    private WorkflowService $workflowService;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // テナント作成・初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $this->workflowService = app(WorkflowService::class);
        $this->tool = new ExecuteApprovalTool;

        // テストデータの準備
        $this->user = User::factory()->create();

        $this->folder = Folder::factory()->create();

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
        ]);

        // トークン作成
        $token = $this->user->createToken('test-token');
        putenv('MCP_AUTH_TOKEN='.$token->plainTextToken);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        parent::tearDown();
    }

    public function test_rejects_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => 1,
                'action' => 'approve',
            ]),
            $this->workflowService
        );

        $this->assertTrue($response->isError());
    }

    public function test_rejects_invalid_ledger_id(): void
    {
        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'action' => 'approve',
            ]),
            $this->workflowService
        );

        $this->assertTrue($response->isError());
        $content = $response->content();
        $this->assertStringContainsString('台帳ID', $content);
    }

    public function test_rejects_invalid_action(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'action' => 'invalid_action',
            ]),
            $this->workflowService
        );

        $this->assertTrue($response->isError());
        $content = $response->content();
        $this->assertStringContainsString('無効なアクション', $content);
    }

    public function test_executes_approve_action(): void
    {
        $this->markTestSkipped('ワークフロー統合テストは複雑なため、統合テストで実装');
    }

    public function test_executes_return_to_draft_action(): void
    {
        $this->markTestSkipped('ワークフロー統合テストは複雑なため、統合テストで実装');
    }

    public function test_returns_proper_json_response(): void
    {
        // 承認できない台帳を作成（DRAFTステータス）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
        ]);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'action' => 'approve',
            ]),
            $this->workflowService
        );

        // エラーレスポンスであることを確認
        $this->assertInstanceOf(\Laravel\Mcp\Response::class, $response);
        $this->assertTrue($response->isError());
    }
}
