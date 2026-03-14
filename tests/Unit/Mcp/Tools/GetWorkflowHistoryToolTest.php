<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\GetWorkflowHistoryTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use App\Models\User;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class GetWorkflowHistoryToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private GetWorkflowHistoryTool $tool;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    private \App\Repositories\WritableFolderRepository $folderRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->folderRepository = \Mockery::mock(\App\Repositories\WritableFolderRepository::class);
        $this->folderRepository->allows('clearAllCache')->andReturn(null);
        $this->folderRepository->allows('refreshAllCache')->andReturn(null);
        $this->folderRepository->allows('getReadableFolderIds')
            ->andReturnUsing(fn () => isset($this->folder) ? [$this->folder->id] : []);
        $this->folderRepository->allows('getAccessibleFolderIds')
            ->andReturnUsing(fn () => isset($this->folder) ? [$this->folder->id] : []);
        $this->app->instance(\App\Repositories\WritableFolderRepository::class, $this->folderRepository);

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

    private function mockReadableFolderAccess(): void
    {
        $this->folderRepository->allows('getReadableFolderIds')
            ->andReturn([$this->folder->id]);
        $this->folderRepository->allows('getAccessibleFolderIds')
            ->andReturn([$this->folder->id]);
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
        $this->mockReadableFolderAccess();

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
        $this->mockReadableFolderAccess();

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
        $this->mockReadableFolderAccess();

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
        $this->mockReadableFolderAccess();

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

    public function test_returns_latest_vs_previous_comparison_when_requested(): void
    {
        $this->mockReadableFolderAccess();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'version' => 3,
            'content' => ['Contract A', '2026-03-31'],
        ]);

        $columnDefine = [
            ['id' => 0, 'name' => '件名', 'type' => 'text', 'order' => 1],
            ['id' => 1, 'name' => '契約期間', 'type' => 'text', 'order' => 2],
        ];

        $oldDiff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 2,
            'content' => ['Contract A', '2025-12-31'],
            'column_define' => $columnDefine,
            'created_at' => now()->subDay(),
        ]);

        $newDiff = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'version' => 3,
            'content' => ['Contract A', '2026-03-31'],
            'column_define' => $columnDefine,
            'comments' => '契約期間を延長',
            'created_at' => now(),
        ]);

        $response = $this->tool->handle(new \Laravel\Mcp\Request([
            'ledger_id' => $ledger->id,
            'compare_latest_vs_previous' => true,
            'format' => 'raw',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true);

        $this->assertArrayHasKey('comparison', $content);
        $this->assertTrue($content['comparison']['has_changes']);
        $this->assertSame('latest_vs_previous', $content['comparison']['mode']);
        $this->assertSame($newDiff->id, $content['comparison']['base_diff']['id']);
        $this->assertSame($oldDiff->id, $content['comparison']['target_diff']['id']);
        $this->assertSame(1, $content['comparison']['changed_fields_count']);
        $this->assertSame('契約期間', $content['comparison']['changed_fields'][0]['column_name']);
        $this->assertSame('2025-12-31', $content['comparison']['changed_fields'][0]['before_text']);
        $this->assertSame('2026-03-31', $content['comparison']['changed_fields'][0]['after_text']);
        $this->assertStringContainsString('更新者は', $content['comparison']['summary']);
    }

    public function test_returns_manual_comparison_for_selected_diffs(): void
    {
        $this->mockReadableFolderAccess();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'version' => 4,
            'content' => ['案件B', '承認済み'],
        ]);

        $columnDefine = [
            ['id' => 0, 'name' => '件名', 'type' => 'text', 'order' => 1],
            ['id' => 1, 'name' => '状態', 'type' => 'text', 'order' => 2],
        ];

        $diff1 = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'version' => 1,
            'content' => ['案件B', '作成中'],
            'column_define' => $columnDefine,
            'created_at' => now()->subDays(3),
        ]);

        $diff2 = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
            'version' => 2,
            'content' => ['案件B', '承認待ち'],
            'column_define' => $columnDefine,
            'created_at' => now()->subDays(2),
        ]);

        $diff3 = LedgerDiff::factory()->create([
            'ledger_id' => $ledger->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'version' => 3,
            'content' => ['案件B', '承認済み'],
            'column_define' => $columnDefine,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->tool->handle(new \Laravel\Mcp\Request([
            'ledger_id' => $ledger->id,
            'base_diff_id' => $diff3->id,
            'target_diff_id' => $diff1->id,
            'format' => 'summary',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true);

        $this->assertSame('manual', $content['comparison']['mode']);
        $this->assertSame($diff3->version, $content['comparison']['base_diff']['version']);
        $this->assertSame($diff1->version, $content['comparison']['target_diff']['version']);
        $this->assertSame(1, $content['comparison']['changed_fields_count']);
        $this->assertSame('状態', $content['comparison']['changed_fields'][0]['column_name']);
        $this->assertSame('作成中', $content['comparison']['changed_fields'][0]['before_text']);
        $this->assertSame('承認済み', $content['comparison']['changed_fields'][0]['after_text']);
        $this->assertStringContainsString('Ver.1', $content['comparison']['summary']);

        $this->assertCount(3, $content['history']);
        $this->assertSame($diff2->id, $content['history'][1]['id']);
    }

    public function test_requires_base_diff_when_only_target_diff_is_given(): void
    {
        $this->mockReadableFolderAccess();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
        ]);

        $response = $this->tool->handle(new \Laravel\Mcp\Request([
            'ledger_id' => $ledger->id,
            'target_diff_id' => 999,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('基準', $response->content());
    }
}
