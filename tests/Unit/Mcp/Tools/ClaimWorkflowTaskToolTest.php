<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\ClaimWorkflowTaskTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class ClaimWorkflowTaskToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private ClaimWorkflowTaskTool $tool;

    private WorkflowService $workflowService;

    private User $user;

    private User $originalAssignee;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->workflowService = app(WorkflowService::class);
        $this->tool = new ClaimWorkflowTaskTool;

        // テストデータの準備
        $this->user = User::factory()->create();
        $this->originalAssignee = User::factory()->create();

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
        \Mockery::close();
        parent::tearDown();
    }

    public function test_rejects_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => 1,
            ]),
            $this->workflowService
        );

        $this->assertTrue($response->isError());
    }

    public function test_rejects_missing_ledger_id(): void
    {
        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([]),
            $this->workflowService
        );

        $this->assertTrue($response->isError());
        $content = $response->content();
        $this->assertStringContainsString('台帳ID', $content);
    }

    public function test_returns_error_for_non_existent_ledger(): void
    {
        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => 99999,
            ]),
            $this->workflowService
        );

        $this->assertTrue($response->isError());
        $content = $response->content();
        $this->assertStringContainsString('見つかりません', $content);
    }

    public function test_claims_inspection_task_successfully(): void
    {
        // 実際の台帳を作成（点検待ちステータス）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->originalAssignee->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        // WorkflowServiceをモック
        $mockWorkflowService = \Mockery::mock(WorkflowService::class);

        // claimTask呼び出しのモック（台帳を返す）
        $mockWorkflowService->shouldReceive('claimTask')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($ledger) {
                return $arg instanceof Ledger && $arg->id === $ledger->id;
            }), \Mockery::on(function ($arg) {
                return $arg instanceof User && $arg->id === $this->user->id;
            }), \Mockery::type('string'))
            ->andReturn($ledger);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'comments' => 'テスト引き継ぎ',
            ]),
            $mockWorkflowService
        );

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertEquals('success', $responseData['type']);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('ledger', $responseData);
        $this->assertStringContainsString($this->user->name, $responseData['__summary__']);
    }

    public function test_claims_approval_task_successfully(): void
    {
        // 実際の台帳を作成（承認待ちステータス）
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->originalAssignee->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
        ]);

        // WorkflowServiceをモック
        $mockWorkflowService = \Mockery::mock(WorkflowService::class);

        $mockWorkflowService->shouldReceive('claimTask')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($ledger) {
                return $arg instanceof Ledger && $arg->id === $ledger->id;
            }), \Mockery::on(function ($arg) {
                return $arg instanceof User && $arg->id === $this->user->id;
            }), \Mockery::type('string'))
            ->andReturn($ledger);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'comments' => '承認タスク引き継ぎ',
            ]),
            $mockWorkflowService
        );

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertEquals('success', $responseData['type']);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('ledger', $responseData);
        $this->assertEquals($ledger->id, $responseData['ledger']['id']);
    }

    public function test_handles_service_exceptions(): void
    {
        $mockWorkflowService = \Mockery::mock(WorkflowService::class);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->originalAssignee->id,
            'status' => WorkflowStatus::DRAFT, // 引き継ぎできないステータス
        ]);

        $mockWorkflowService->shouldReceive('claimTask')
            ->once()
            ->andThrow(new \Exception('引き継ぎできません'));

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
            ]),
            $mockWorkflowService
        );

        $this->assertTrue($response->isError());
        $content = $response->content();
        $this->assertStringContainsString('引き継ぎできません', $content);
    }

    public function test_response_includes_proper_fields(): void
    {
        // 実際の台帳を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->originalAssignee->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
        ]);

        // WorkflowServiceをモック
        $mockWorkflowService = \Mockery::mock(WorkflowService::class);

        $mockWorkflowService->shouldReceive('claimTask')
            ->once()
            ->andReturn($ledger);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'comments' => 'フィールド確認テスト',
            ]),
            $mockWorkflowService
        );

        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        // 必須フィールドの存在確認
        $this->assertArrayHasKey('type', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertArrayHasKey('__summary__', $responseData);
        $this->assertArrayHasKey('ledger', $responseData);
        $this->assertArrayHasKey('claimed_at', $responseData);
        $this->assertArrayHasKey('comments', $responseData);

        // レスポンス値の検証
        $this->assertEquals('success', $responseData['type']);
        $this->assertEquals('フィールド確認テスト', $responseData['comments']);
    }
}
