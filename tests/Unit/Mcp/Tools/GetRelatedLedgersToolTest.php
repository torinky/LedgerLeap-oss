<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Mcp\Tools\GetRelatedLedgersTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\Ledger\RelatedLedgerService;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

#[CoversClass(GetRelatedLedgersTool::class)]
class GetRelatedLedgersToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private GetRelatedLedgersTool $tool;

    private User $user;

    private Folder $folder;

    private LedgerDefine $ledgerDefine;

    private WritableFolderRepository $folderRepository;

    private RelatedLedgerService $relatedLedgerService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->relatedLedgerService = Mockery::mock(RelatedLedgerService::class);

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

        $this->tool = new GetRelatedLedgersTool($this->relatedLedgerService);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_related_ledgers_in_summary_format(): void
    {
        $sourceLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => '設備点検記録'],
            'content_attached' => [0 => []],
        ])->load(['define', 'define.folder', 'define.folder.ancestors']);

        $relatedLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'version' => 1,
            'content' => [0 => '保守依頼'],
            'content_attached' => [0 => []],
        ])->load(['define', 'define.folder', 'define.folder.ancestors']);

        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(User::class), FolderPermissionType::READ)
            ->andReturn([$this->folder->id]);

        $this->relatedLedgerService->expects()
            ->resolve(
                Mockery::on(fn ($ledger) => $ledger instanceof Ledger && $ledger->id === $sourceLedger->id),
                Mockery::type(User::class),
                true,
                true,
                20,
            )
            ->andReturn([
                'identifier_keys' => ['EQ-001' => ['source' => 'auto_number', 'column' => '管理番号']],
                'identifier_results' => collect(),
                'semantic_results' => collect(),
                'merged' => [[
                    'ledger' => $relatedLedger,
                    'reason' => 'identifier',
                    'score' => null,
                    'matched_keys' => [[
                        'value' => 'EQ-001',
                        'source' => 'auto_number',
                        'column' => '管理番号',
                    ]],
                ]],
                'identifier_count' => 1,
                'semantic_count' => 0,
                'total_count' => 1,
                'returned_count' => 1,
                'has_auto_number' => true,
                'rag_available' => true,
                'last_error' => '',
            ]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $sourceLedger->id,
            'format' => 'summary',
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('__summary__', $content);
        $this->assertSame(1, $content['total_count']);
        $this->assertSame('設備点検記録', $content['source_ledger']['title']);
        $this->assertSame('identifier', $content['related_ledgers'][0]['reason']);
        $this->assertSame('識別番号', $content['related_ledgers'][0]['reason_label']);
        $this->assertSame(['EQ-001（識別番号列）'], $content['related_ledgers'][0]['matched_keys_label']);
    }

    #[Test]
    public function it_returns_related_ledgers_in_raw_format(): void
    {
        $sourceLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => 'インシデント報告'],
            'content_attached' => [0 => []],
        ])->load(['define', 'define.folder', 'define.folder.ancestors']);

        $relatedLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => '過去事例'],
            'content_attached' => [0 => []],
        ])->load(['define', 'define.folder', 'define.folder.ancestors']);

        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(User::class), FolderPermissionType::READ)
            ->andReturn([$this->folder->id]);

        $this->relatedLedgerService->expects()
            ->resolve(Mockery::type(Ledger::class), Mockery::type(User::class), true, true, 5)
            ->andReturn([
                'identifier_keys' => [],
                'identifier_results' => collect(),
                'semantic_results' => collect(),
                'merged' => [[
                    'ledger' => $relatedLedger,
                    'reason' => 'semantic',
                    'score' => 0.92,
                    'matched_keys' => [],
                ]],
                'identifier_count' => 0,
                'semantic_count' => 1,
                'total_count' => 1,
                'returned_count' => 1,
                'has_auto_number' => false,
                'rag_available' => true,
                'last_error' => '',
            ]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $sourceLedger->id,
            'format' => 'raw',
            'limit' => 5,
        ]));

        $this->assertFalse($response->isError());

        $content = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('__summary__', $content);
        $this->assertSame('semantic', $content['related_ledgers'][0]['reason']);
        $this->assertSame('92.0%', $content['related_ledgers'][0]['score_label']);
    }

    #[Test]
    public function it_rejects_unreadable_related_ledger_requests(): void
    {
        $sourceLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => '設備点検記録'],
            'content_attached' => [0 => []],
        ])->load(['define', 'define.folder', 'define.folder.ancestors']);

        $this->folderRepository->expects()
            ->getAccessibleFolderIds(Mockery::type(User::class), FolderPermissionType::READ)
            ->andReturn([]);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $sourceLedger->id,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('アクセス権限がありません', $response->content());
    }

    #[Test]
    public function it_rejects_requests_when_both_search_axes_are_disabled(): void
    {
        $sourceLedger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
            'status' => WorkflowStatus::NONE,
            'content' => [0 => '設備点検記録'],
            'content_attached' => [0 => []],
        ])->load(['define', 'define.folder', 'define.folder.ancestors']);

        $response = $this->tool->handle(new Request([
            'ledger_id' => $sourceLedger->id,
            'include_identifier' => false,
            'include_semantic' => false,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('少なくとも一方を有効', $response->content());
    }

    #[Test]
    public function it_rejects_requests_for_missing_ledger(): void
    {
        $response = $this->tool->handle(new Request([
            'ledger_id' => 999999,
        ]));

        $this->assertTrue($response->isError());
        $this->assertStringContainsString('台帳', $response->content());
    }
}
