<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Tools\SearchLedgersTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\LedgerService;
use App\Services\SynonymService;
use Laravel\Mcp\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SearchLedgersToolKeywordSearchTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // ユーザーとトークンを作成
        $token = $this->user->createToken('test')->plainTextToken;

        // MCP_AUTH_TOKEN 環境変数を設定
        putenv('MCP_AUTH_TOKEN='.$token);
    }

    protected function tearDown(): void
    {
        putenv('MCP_AUTH_TOKEN');
        parent::tearDown();
    }

    #[Test]
    public function it_performs_keyword_search_correctly()
    {
        // Arrange
        $folder = Folder::factory()->create(['title' => 'Test Folder']);
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        // Grant permission to the user for the created folder
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Tester']);
        $this->user->assignRole($role);
        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::READ,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['test keyword for search'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['another entry'],
        ]);

        $ledgerService = app(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);

        $request = new Request([
            'q' => 'keyword',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertCount(1, $responseData['ledgers']);
        $this->assertEquals(1, $responseData['total']);
        $this->assertStringContainsString('test keyword for search', json_encode($responseData['ledgers'][0]['content']));
    }

    #[Test]
    public function testSynonymExpansionSearchesInvoiceContent()
    {
        // Arrange
        $folder = Folder::factory()->create(['title' => 'Finance Folder']);
        $ledgerDefine = LedgerDefine::factory()->create(['folder_id' => $folder->id]);

        $role = \Spatie\Permission\Models\Role::create(['name' => 'Finance Reader']);
        $this->user->assignRole($role);
        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::READ,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => ['インボイス対応済み'],
        ]);

        app()->instance(SynonymService::class, new class extends SynonymService
        {
            public function getSynonymsFromWord($word, array $options = [])
            {
                return $word === '請求' ? ['インボイス'] : [];
            }
        });

        $ledgerService = app(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);

        $request = new Request([
            'q' => '請求',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertArrayHasKey('ledgers', $responseData);
        $this->assertCount(1, $responseData['ledgers']);
        $this->assertEquals(1, $responseData['total']);
        $this->assertSame(['インボイス対応済み'], $responseData['ledgers'][0]['content']);
    }

    #[Test]
    public function it_respects_folder_permissions_for_keyword_search()
    {
        // Arrange
        $readableFolder = Folder::factory()->create(['title' => 'Readable Folder']);
        $restrictedFolder = Folder::factory()->create(['title' => 'Restricted Folder']);

        $readableDefine = LedgerDefine::factory()->create(['folder_id' => $readableFolder->id]);
        $restrictedDefine = LedgerDefine::factory()->create(['folder_id' => $restrictedFolder->id]);

        Ledger::factory()->create([
            'ledger_define_id' => $readableDefine->id,
            'content' => ['readable keyword'],
        ]);
        Ledger::factory()->create([
            'ledger_define_id' => $restrictedDefine->id,
            'content' => ['restricted keyword'],
        ]);

        // 権限を持つユーザーを作成
        $role = \Spatie\Permission\Models\Role::create(['name' => 'Reader']);
        $this->user->assignRole($role);
        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $readableFolder->id,
            'permission' => \App\Enums\FolderPermissionType::READ,
            'creator_id' => $this->user->id,
            'modifier_id' => $this->user->id,
        ]);

        $ledgerService = app(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);

        $request = new Request([
            'q' => 'keyword',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertCount(1, $responseData['ledgers']);
        $this->assertEquals(1, $responseData['total']);
        $this->assertStringContainsString('readable keyword', json_encode($responseData['ledgers'][0]['content']));
    }

    #[Test]
    public function it_does_not_find_ledgers_in_restricted_folders_for_keyword_search()
    {
        // Arrange
        $restrictedFolder = Folder::factory()->create(['title' => 'Very Restricted Folder']);
        $restrictedDefine = LedgerDefine::factory()->create(['folder_id' => $restrictedFolder->id]);

        Ledger::factory()->create([
            'ledger_define_id' => $restrictedDefine->id,
            'content' => ['super secret keyword'],
        ]);

        // ユーザーにはこのフォルダへの権限がない

        $ledgerService = app(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);

        $request = new Request([
            'q' => 'secret',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
        $responseData = json_decode($response->content(), true);

        $this->assertCount(0, $responseData['ledgers']);
        $this->assertEquals(0, $responseData['total']);
    }
}
