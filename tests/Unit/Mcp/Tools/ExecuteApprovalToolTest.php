<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\ExecuteApprovalTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ExecuteApprovalToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private ExecuteApprovalTool $tool;

    private WorkflowService $workflowService;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

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
            new Request([
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
            new Request([
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
            new Request([
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
        // 実際の台帳を作成（承認待ちステータス）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        // WorkflowServiceをモック
        $mockWorkflowService = \Mockery::mock(WorkflowService::class);

        // canApproveのモック（権限チェック）
        $mockWorkflowService->shouldReceive('canApprove')
            ->once()
            ->with(\Mockery::on(function ($arg) {
                return $arg instanceof User && $arg->id === $this->user->id;
            }), \Mockery::on(function ($arg) use ($ledger) {
                return $arg instanceof Ledger && $arg->id === $ledger->id;
            }))
            ->andReturn(true);

        // 承認後の台帳
        $approvedLedger = $ledger->replicate();
        $approvedLedger->status = WorkflowStatus::APPROVED;
        $approvedLedger->id = $ledger->id;

        // approveメソッドのモック
        $mockWorkflowService->shouldReceive('approve')
            ->once()
            ->with($ledger->id, $this->user->id, \Mockery::type('string'), null)
            ->andReturn($approvedLedger);

        $response = $this->tool->handle(
            new Request([
                'ledger_id' => $ledger->id,
                'action' => 'approve',
                'comments' => '承認します',
            ]),
            $mockWorkflowService
        );

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertEquals('success', $responseData['type']);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('ledger', $responseData);
    }

    public function test_executes_return_to_draft_action(): void
    {
        // 実際の台帳を作成（承認待ちステータス）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        // WorkflowServiceをモック
        $mockWorkflowService = \Mockery::mock(WorkflowService::class);

        // canReturnToDraftのモック（権限チェック）
        $mockWorkflowService->shouldReceive('canReturnToDraft')
            ->once()
            ->with(\Mockery::on(function ($arg) {
                return $arg instanceof User && $arg->id === $this->user->id;
            }), \Mockery::on(function ($arg) use ($ledger) {
                return $arg instanceof Ledger && $arg->id === $ledger->id;
            }))
            ->andReturn(true);

        // 作成中に戻した後の台帳
        $returnedLedger = $ledger->replicate();
        $returnedLedger->status = WorkflowStatus::DRAFT;
        $returnedLedger->id = $ledger->id;

        // returnToDraftメソッドのモック
        $mockWorkflowService->shouldReceive('returnToDraft')
            ->once()
            ->with($ledger->id, $this->user->id, \Mockery::type('string'))
            ->andReturn($returnedLedger);

        $response = $this->tool->handle(
            new Request([
                'ledger_id' => $ledger->id,
                'action' => 'return_to_draft',
                'comments' => '修正が必要です',
            ]),
            $mockWorkflowService
        );

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertEquals('success', $responseData['type']);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('ledger', $responseData);
        $this->assertStringContainsString('作成中', $responseData['__summary__']);
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
            new Request([
                'ledger_id' => $ledger->id,
                'action' => 'approve',
            ]),
            $this->workflowService
        );

        // エラーレスポンスであることを確認
        $this->assertInstanceOf(Response::class, $response);
        $this->assertTrue($response->isError());
    }
}
