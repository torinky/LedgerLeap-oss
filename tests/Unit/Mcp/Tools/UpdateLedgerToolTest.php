<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Mcp\Tools\UpdateLedgerTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Repositories\WritableFolderRepository;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UpdateLedgerTool::class)]
class UpdateLedgerToolTest extends TestCase
{
    use RefreshDatabase;

    private UpdateLedgerTool $tool;

    private \App\Models\User $user;

    private Folder $folder;

    private LedgerDefine $directLedgerDefine;

    private LedgerDefine $workflowLedgerDefine;

    private WritableFolderRepository $folderRepository;

    private LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = \App\Models\Tenant::factory()->create();
        tenancy()->initialize($tenant);

        $this->folderRepository = Mockery::mock(WritableFolderRepository::class);
        $this->folderRepository->allows()->clearAllCache(Mockery::any())->andReturnNull();
        $this->folderRepository->allows()->refreshAllCache(Mockery::any())->andReturnNull();
        $this->app->instance(WritableFolderRepository::class, $this->folderRepository);

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->tool = new UpdateLedgerTool($this->ledgerService);

        $this->user = \App\Models\User::factory()->create();
        $token = $this->user->createToken('test-token', ['mcp:*']);
        putenv('MCP_AUTH_TOKEN='.$token->plainTextToken);

        $this->folder = Folder::factory()->create();
        $this->directLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => false,
        ]);
        $this->workflowLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_previews_changed_columns_without_saving_when_dry_run_is_enabled(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => 'Before update'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->ledgerService->expects()
            ->previewLedgerUpdateForApi($ledger, [
                'content_patch' => [0 => 'After update'],
                'comment' => 'Preview only',
            ])
            ->andReturn([
                'ledger' => $ledger,
                'content_patch' => [0 => 'After update'],
                'comment' => 'Preview only',
                'new_content' => [0 => 'After update'],
                'changed_columns' => [[
                    'column_id' => 0,
                    'column_name' => 'test_field',
                    'before' => 'Before update',
                    'after' => 'After update',
                ]],
                'previous_status' => WorkflowStatus::NONE->value,
                'returns_to_draft_on_save' => false,
            ]);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"After update"}',
            'comment' => 'Preview only',
            'dry_run' => true,
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($content['dry_run']);
        $this->assertSame('After update', $content['content_after_patch'][0]);
        $this->assertSame('Before update', $content['changed_columns'][0]['before']);
        $this->assertSame('After update', $content['changed_columns'][0]['after']);
    }

    #[Test]
    public function it_updates_non_workflow_ledgers_and_returns_summary_metadata(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => 'Before update'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);
        $updatedLedger = clone $ledger;
        $updatedLedger->content = [0 => 'After update'];
        $updatedLedger->version = 2;

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->ledgerService->expects()
            ->previewLedgerUpdateForApi($ledger, [
                'content_patch' => [0 => 'After update'],
                'comment' => 'Apply update',
            ])
            ->andReturn([
                'ledger' => $ledger,
                'content_patch' => [0 => 'After update'],
                'comment' => 'Apply update',
                'new_content' => [0 => 'After update'],
                'changed_columns' => [[
                    'column_id' => 0,
                    'column_name' => 'test_field',
                    'before' => 'Before update',
                    'after' => 'After update',
                ]],
                'previous_status' => WorkflowStatus::NONE->value,
                'returns_to_draft_on_save' => false,
            ]);
        $this->ledgerService->expects()
            ->updateLedgerForApi(Mockery::type(\App\Models\User::class), $ledger, [
                'content_patch' => [0 => 'After update'],
                'comment' => 'Apply update',
            ])
            ->andReturn($updatedLedger);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"After update"}',
            'comment' => 'Apply update',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($content['dry_run']);
        $this->assertSame('After update', $content['ledger']['content_by_column_id'][0]);
        $this->assertSame(WorkflowStatus::NONE->value, $content['meta']['current_status']);
        $this->assertFalse($content['meta']['returned_to_draft']);
        $this->assertSame(1, $content['meta']['change_count']);
    }

    #[Test]
    public function it_returns_pending_inspection_ledgers_to_draft_when_updated(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_INSPECTION,
            'version' => 1,
            'content' => [0 => 'Before review fix'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);
        $updatedLedger = clone $ledger;
        $updatedLedger->status = WorkflowStatus::DRAFT;
        $updatedLedger->content = [0 => 'After review fix'];

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->ledgerService->expects()
            ->previewLedgerUpdateForApi($ledger, [
                'content_patch' => [0 => 'After review fix'],
                'comment' => '差し戻し内容を反映',
            ])
            ->andReturn([
                'ledger' => $ledger,
                'content_patch' => [0 => 'After review fix'],
                'comment' => '差し戻し内容を反映',
                'new_content' => [0 => 'After review fix'],
                'changed_columns' => [[
                    'column_id' => 0,
                    'column_name' => 'test_field',
                    'before' => 'Before review fix',
                    'after' => 'After review fix',
                ]],
                'previous_status' => WorkflowStatus::PENDING_INSPECTION->value,
                'returns_to_draft_on_save' => true,
            ]);
        $this->ledgerService->expects()
            ->updateLedgerForApi(Mockery::type(\App\Models\User::class), $ledger, [
                'content_patch' => [0 => 'After review fix'],
                'comment' => '差し戻し内容を反映',
            ])
            ->andReturn($updatedLedger);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"After review fix"}',
            'comment' => '差し戻し内容を反映',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(WorkflowStatus::DRAFT->value, $content['ledger']['workflow']['status']);
        $this->assertSame(WorkflowStatus::PENDING_INSPECTION->value, $content['meta']['previous_status']);
        $this->assertTrue($content['meta']['returned_to_draft']);
    }

    #[Test]
    public function it_returns_pending_approval_ledgers_to_draft_when_updated(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::PENDING_APPROVAL,
            'version' => 1,
            'content' => [0 => 'Before approval fix'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);
        $updatedLedger = clone $ledger;
        $updatedLedger->status = WorkflowStatus::DRAFT;
        $updatedLedger->content = [0 => 'After approval fix'];

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->ledgerService->expects()
            ->previewLedgerUpdateForApi($ledger, [
                'content_patch' => [0 => 'After approval fix'],
                'comment' => '承認前の修正を反映',
            ])
            ->andReturn([
                'ledger' => $ledger,
                'content_patch' => [0 => 'After approval fix'],
                'comment' => '承認前の修正を反映',
                'new_content' => [0 => 'After approval fix'],
                'changed_columns' => [[
                    'column_id' => 0,
                    'column_name' => 'test_field',
                    'before' => 'Before approval fix',
                    'after' => 'After approval fix',
                ]],
                'previous_status' => WorkflowStatus::PENDING_APPROVAL->value,
                'returns_to_draft_on_save' => true,
            ]);
        $this->ledgerService->expects()
            ->updateLedgerForApi(Mockery::type(\App\Models\User::class), $ledger, [
                'content_patch' => [0 => 'After approval fix'],
                'comment' => '承認前の修正を反映',
            ])
            ->andReturn($updatedLedger);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"After approval fix"}',
            'comment' => '承認前の修正を反映',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(WorkflowStatus::DRAFT->value, $content['ledger']['workflow']['status']);
        $this->assertSame(WorkflowStatus::PENDING_APPROVAL->value, $content['meta']['previous_status']);
        $this->assertTrue($content['meta']['returned_to_draft']);
    }

    #[Test]
    public function it_rejects_invalid_column_ids(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => 'Before update'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->ledgerService->expects()
            ->previewLedgerUpdateForApi($ledger, [
                'content_patch' => [999 => 'Unknown column'],
                'comment' => null,
            ])
            ->andThrow(ValidationException::withMessages([
                'content_patch' => ['Unknown column definition id(s): 999'],
            ]));
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"999":"Unknown column"}',
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('Unknown column definition id(s): 999', $response->content());
    }

    #[Test]
    public function it_rejects_tag_update_inputs_for_the_initial_mcp_contract(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => 'Before update'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"After update"}',
            'tag_operation' => 'replace',
            'tag_values' => ['urgent'],
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('タグ更新はまだ初期のMCP更新契約ではサポートされていません', $response->content());
    }

    #[Test]
    public function it_rejects_updates_without_write_permission(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->directLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => 'Before update'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"After update"}',
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('アクセス権限がありません', $response->content());
    }

    #[Test]
    public function it_rejects_approved_ledgers(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->workflowLedgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'content' => [0 => 'Approved content'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::type(Ledger::class))
            ->andReturn($ledger);
        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(\App\Models\User::class), FolderPermissionType::WRITE)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'content_patch' => '{"0":"Should fail"}',
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('承認済み', $response->content());
    }
}
