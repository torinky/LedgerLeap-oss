<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\ClaimWorkflowTaskTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class ClaimWorkflowTaskToolTest extends TestCase
{
    use DatabaseMigrations;

    private ClaimWorkflowTaskTool $tool;

    private WorkflowService $workflowService;

    private User $user;

    private User $originalAssignee;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // テナント作成・初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

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
        $this->markTestSkipped('ワークフロー統合テストは複雑なため、統合テストで実装');
    }

    public function test_claims_approval_task_successfully(): void
    {
        $this->markTestSkipped('ワークフロー統合テストは複雑なため、統合テストで実装');
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
        $this->markTestSkipped('ワークフロー統合テストは複雑なため、統合テストで実装');
    }
}
