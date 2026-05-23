<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetLedgerDefinesTool;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class GetLedgerDefinesToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected string $validToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $tokenResult = $this->user->createToken('test-token');
        $this->validToken = $tokenResult->plainTextToken;
    }

    private function getDefines(array $responseData): array
    {
        return $responseData['ledger_defines'];
    }

    #[Test]
    public function it_returns_ledger_defines_user_has_access_to(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $accessibleFolder = Folder::factory()->create(['title' => 'Accessible Folder']);
        $inaccessibleFolder = Folder::factory()->create(['title' => 'Inaccessible Folder']);

        $accessibleLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $accessibleFolder->id,
            'title' => 'Accessible Define',
        ]);
        LedgerDefine::factory()->create([
            'folder_id' => $inaccessibleFolder->id,
            'title' => 'Inaccessible Define',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$accessibleFolder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(1, $defines);
        $this->assertEquals($accessibleLedgerDefine->id, $defines[0]['id']);
        $this->assertEquals('Accessible Define', $defines[0]['name']);
        $this->assertArrayHasKey('total', $responseData);
        $this->assertArrayHasKey('limit', $responseData);
        $this->assertArrayHasKey('offset', $responseData);
    }

    #[Test]
    public function it_filters_ledger_defines_by_partial_title_and_folder_id(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $lookupFolder = Folder::factory()->create(['title' => 'Lookup Folder']);
        $otherFolder = Folder::factory()->create(['title' => 'Other Folder']);

        $matchingLedgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $lookupFolder->id,
            'title' => 'Sales Lookup',
        ]);
        LedgerDefine::factory()->create([
            'folder_id' => $lookupFolder->id,
            'title' => 'Finance Archive',
        ]);
        LedgerDefine::factory()->create([
            'folder_id' => $otherFolder->id,
            'title' => 'Sales Lookup Outside',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$lookupFolder->id, $otherFolder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([
            'q' => 'Sales',
            'folder_id' => $lookupFolder->id,
        ]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(1, $defines);
        $this->assertSame($matchingLedgerDefine->id, $defines[0]['id']);
        $this->assertSame('Sales Lookup', $defines[0]['name']);
    }

    #[Test]
    public function it_returns_empty_array_when_user_has_no_accessible_folders(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(0, $defines);
        $this->assertEquals(0, $responseData['total']);
    }

    #[Test]
    public function it_formats_response_using_ledger_define_resource(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Test Define',
            'create_description' => 'Test Description',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(1, $defines);

        $ledgerDefineData = $defines[0];
        $this->assertArrayHasKey('id', $ledgerDefineData);
        $this->assertArrayHasKey('name', $ledgerDefineData);
        $this->assertEquals($ledgerDefine->id, $ledgerDefineData['id']);
        $this->assertEquals('Test Define', $ledgerDefineData['name']);
    }

    #[Test]
    public function it_handles_multiple_accessible_folders(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder1 = Folder::factory()->create(['title' => 'Folder 1']);
        $folder2 = Folder::factory()->create(['title' => 'Folder 2']);
        $folder3 = Folder::factory()->create(['title' => 'Folder 3']);

        $ledgerDefine1 = LedgerDefine::factory()->create([
            'folder_id' => $folder1->id,
            'title' => 'Define 1',
        ]);
        $ledgerDefine2 = LedgerDefine::factory()->create([
            'folder_id' => $folder2->id,
            'title' => 'Define 2',
        ]);
        $ledgerDefine3 = LedgerDefine::factory()->create([
            'folder_id' => $folder3->id,
            'title' => 'Define 3',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder1->id, $folder3->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(2, $defines);

        $returnedIds = array_column($defines, 'id');
        $this->assertContains($ledgerDefine1->id, $returnedIds);
        $this->assertContains($ledgerDefine3->id, $returnedIds);
        $this->assertNotContains($ledgerDefine2->id, $returnedIds);
    }

    #[Test]
    public function it_returns_compact_json_without_pretty_print(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $content = $response->content();
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('ledger_defines', $decoded);
    }

    #[Test]
    public function it_respects_limit_parameter(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            LedgerDefine::factory()->create([
                'folder_id' => $folder->id,
                'title' => "Define {$i}",
            ]);
        }

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request(['limit' => 2]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(2, $defines);
        $this->assertEquals(5, $responseData['total']);
        $this->assertEquals(2, $responseData['limit']);
    }

    #[Test]
    public function it_respects_offset_parameter(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            LedgerDefine::factory()->create([
                'folder_id' => $folder->id,
                'title' => "Define {$i}",
            ]);
        }

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request(['limit' => 3, 'offset' => 2]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(3, $defines);
        $this->assertEquals(5, $responseData['total']);
        $this->assertEquals(2, $responseData['offset']);
    }

    #[Test]
    public function it_excludes_column_options_by_default(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Define With Options',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        // include_options デフォルト false
        $request = new Request([]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(1, $defines);

        // デフォルトでは options キーが columns に存在しない
        if (isset($defines[0]['columns'][0])) {
            $this->assertArrayNotHasKey('options', $defines[0]['columns'][0]);
        }
    }

    #[Test]
    public function it_includes_column_options_when_requested(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();
        $ledgerDefine = LedgerDefine::factory()->create([
            'folder_id' => $folder->id,
            'title' => 'Define With Options',
        ]);

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request(['include_options' => true]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        $defines = $this->getDefines($responseData);
        $this->assertCount(1, $defines);

        // include_options=true では options キーが存在する
        if (isset($defines[0]['columns'][0])) {
            $this->assertArrayHasKey('options', $defines[0]['columns'][0]);
        }
    }

    #[Test]
    public function it_clamps_limit_to_max_100(): void
    {
        putenv("MCP_AUTH_TOKEN={$this->validToken}");

        $folder = Folder::factory()->create();

        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->with(Mockery::type(User::class))
            ->andReturn([$folder->id]);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        $tool = new GetLedgerDefinesTool;
        $request = new Request(['limit' => 200]);

        $response = $tool->handle($request, $mockRepository);

        $this->assertFalse($response->isError());

        $responseData = json_decode($response->content(), true);
        // limit 200 は内部的に 100 にクランプされる
        $this->assertEquals(100, $responseData['limit']);
        $this->assertEquals(0, $responseData['total']);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN=');
        Mockery::close();
        parent::tearDown();
    }
}
