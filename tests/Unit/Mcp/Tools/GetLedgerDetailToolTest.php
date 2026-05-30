<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Mcp\Tools\GetLedgerDetailTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(GetLedgerDetailTool::class)]
class GetLedgerDetailToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private GetLedgerDetailTool $tool;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    private WritableFolderRepository $folderRepository;

    private LedgerService $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->ledgerService = Mockery::mock(LedgerService::class);

        $this->user = User::factory()->create();
        $token = $this->user->createToken('test-token', ['mcp:*']);
        putenv('MCP_AUTH_TOKEN='.$token->plainTextToken);

        $this->folderRepository = Mockery::mock(WritableFolderRepository::class);
        $this->folderRepository->allows()->clearAllCache(Mockery::any())->andReturnNull();
        $this->folderRepository->allows()->refreshAllCache(Mockery::any())->andReturnNull();
        $this->app->instance(WritableFolderRepository::class, $this->folderRepository);

        $this->folder = Folder::factory()->create();
        $this->ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
            'workflow_enabled' => false,
        ]);

        $this->tool = new GetLedgerDetailTool($this->ledgerService);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_single_ledger_detail_in_summary_format(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => 'Current value'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::on(fn ($arg) => $arg instanceof Ledger && $arg->id === $ledger->id))
            ->andReturn($ledger);

        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(User::class), FolderPermissionType::READ)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'format' => 'summary',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Current value', $content['ledger']['content_by_column_id'][0]);
        $this->assertSame(WorkflowStatus::NONE->value, $content['ledger']['workflow']['status']);
        $this->assertArrayHasKey('__summary__', $content);
        $this->assertSame('Current value', $content['summary']['title']);
    }

    #[Test]
    public function it_returns_single_ledger_detail_in_raw_format(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => 'Raw current value'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::on(fn ($arg) => $arg instanceof Ledger && $arg->id === $ledger->id))
            ->andReturn($ledger);

        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(User::class), FolderPermissionType::READ)
            ->andReturn([$this->folder->id]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
            'format' => 'raw',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('ledger', $content);
        $this->assertArrayNotHasKey('__summary__', $content);
        $this->assertSame('Raw current value', $content['ledger']['content_by_column_id'][0]);
    }

    #[Test]
    public function it_rejects_unreadable_ledger_detail_requests(): void
    {
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => 'Current value'],
            'content_attached' => [0 => []],
        ])->load([
            'define',
            'define.folder',
            'define.folder.ancestors',
            'define.tags',
            'latestDiff',
        ]);

        $this->ledgerService->expects()
            ->getLedgerForApi(Mockery::on(fn ($arg) => $arg instanceof Ledger && $arg->id === $ledger->id))
            ->andReturn($ledger);

        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(User::class), FolderPermissionType::READ)
            ->andReturn([]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $ledger->id,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('アクセス権限がありません', $response->content());
    }
}
