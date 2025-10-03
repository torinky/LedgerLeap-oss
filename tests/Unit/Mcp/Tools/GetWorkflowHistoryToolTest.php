<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\GetWorkflowHistoryTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class GetWorkflowHistoryToolTest extends TestCase
{
    use DatabaseMigrations;

    private GetWorkflowHistoryTool $tool;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        // テナント作成・初期化
        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $this->tool = new GetWorkflowHistoryTool;

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
        \Mockery::close();
        parent::tearDown();
    }

    public function test_rejects_missing_token(): void
    {
        putenv('MCP_AUTH_TOKEN=');

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => 1,
            ])
        );

        $this->assertTrue($response->isError());
    }

    public function test_rejects_missing_ledger_id(): void
    {
        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([])
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
            ])
        );

        $this->assertTrue($response->isError());
        $content = $response->content();
        $this->assertStringContainsString('見つかりません', $content);
    }

    public function test_returns_workflow_history_with_raw_format(): void
    {
        // WritableFolderRepositoryをモック（権限付与）
        $mockRepository = \Mockery::mock(\App\Repositories\WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('getAccessibleFolderIds')->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('getAccessibleFolderIds')
            ->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('clearAllCache')->andReturn(null);
        $mockRepository->shouldReceive('refreshAllCache')->andReturn(null);
        $this->app->instance(\App\Repositories\WritableFolderRepository::class, $mockRepository);

        // 台帳とその履歴を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
        ]);

        // ワークフロー履歴を作成
        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
        ]);

        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'inspector_id' => $this->user->id,
            'version' => 1,
        ]);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'format' => 'raw',
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertArrayHasKey('history', $content);
        $this->assertArrayHasKey('__summary__', $content);
        $this->assertArrayHasKey('__display_fields__', $content);
        $this->assertCount(2, $content['history']);
        $this->assertEquals(2, $content['total_count']);
    }

    public function test_returns_workflow_history_with_summary_format(): void
    {
        // WritableFolderRepositoryをモック（権限付与）
        $mockRepository = \Mockery::mock(\App\Repositories\WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('getAccessibleFolderIds')->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('clearAllCache')->andReturn(null);
        $mockRepository->shouldReceive('refreshAllCache')->andReturn(null);
        $this->app->instance(\App\Repositories\WritableFolderRepository::class, $mockRepository);

        // 台帳とその履歴を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
        ]);

        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
        ]);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'format' => 'summary',
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertArrayHasKey('history', $content);
        $this->assertArrayHasKey('__summary__', $content);
        $this->assertArrayHasKey('__display_fields__', $content);
        $this->assertArrayHasKey('total_count', $content);
        $this->assertArrayHasKey('ledger_id', $content);
    }

    public function test_history_includes_proper_fields(): void
    {
        // WritableFolderRepositoryをモック（権限付与）
        $mockRepository = \Mockery::mock(\App\Repositories\WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('getAccessibleFolderIds')->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('clearAllCache')->andReturn(null);
        $mockRepository->shouldReceive('refreshAllCache')->andReturn(null);
        $this->app->instance(\App\Repositories\WritableFolderRepository::class, $mockRepository);

        // 台帳とその履歴を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
        ]);

        $approver = User::factory()->create();

        LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'approver_id' => $approver->id,
            'version' => 1,
            'comments' => 'テストコメント',
        ]);

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $history = $content['history'][0];
        $this->assertArrayHasKey('id', $history);
        $this->assertArrayHasKey('version', $history);
        $this->assertArrayHasKey('created_at', $history);
        $this->assertArrayHasKey('created_at_formatted', $history);
        $this->assertArrayHasKey('modifier_name', $history);
        $this->assertArrayHasKey('status', $history);
        $this->assertArrayHasKey('status_label', $history);
        $this->assertArrayHasKey('detail', $history);
        $this->assertArrayHasKey('comments', $history);
        $this->assertArrayHasKey('approver_name', $history);

        $this->assertEquals('テストコメント', $history['comments']);
        $this->assertEquals($approver->name, $history['approver_name']);
    }

    public function test_respects_limit_parameter(): void
    {
        // WritableFolderRepositoryをモック（権限付与）
        $mockRepository = \Mockery::mock(\App\Repositories\WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('getAccessibleFolderIds')->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('clearAllCache')->andReturn(null);
        $mockRepository->shouldReceive('refreshAllCache')->andReturn(null);
        $this->app->instance(\App\Repositories\WritableFolderRepository::class, $mockRepository);

        // 台帳を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
        ]);

        // 5件の履歴を作成
        for ($i = 0; $i < 5; $i++) {
            LedgerDiff::factory()->create([
                'ledger_id' => $ledger->id,
                'modifier_id' => $this->user->id,
                'status' => WorkflowStatus::DRAFT,
                'version' => 1,
            ]);
        }

        $response = $this->tool->handle(
            new \Laravel\Mcp\Request([
                'ledger_id' => $ledger->id,
                'limit' => 3,
            ])
        );

        $this->assertFalse($response->isError());
        $content = json_decode($response->content(), true);

        $this->assertCount(3, $content['history']);
        $this->assertEquals(3, $content['total_count']);
    }
}
