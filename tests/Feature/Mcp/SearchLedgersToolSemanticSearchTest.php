<?php

namespace Tests\Feature\Mcp;

use App\Jobs\ProcessLedgerForRagJob;
use App\Mcp\Tools\SearchLedgersTool;
use App\Models\User;
use App\Services\LedgerService;
use Laravel\Mcp\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class SearchLedgersToolSemanticSearchTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected bool $fakeQueue = false;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テスト用ユーザーを作成（DemoCompleteSeedは重いので不要）
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $token = $this->user->createToken('test-token')->plainTextToken;

        // 認証トークンを環境変数に設定
        putenv('MCP_AUTH_TOKEN='.$token);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        putenv('MCP_AUTH_TOKEN'); // 環境変数をクリーンアップ
    }

    #[Group('semantic-search')]
    #[Test]
    public function it_performs_semantic_search_via_mcp_when_semantic_score_is_specified()
    {
        // Arrange
        $ledgerServiceMock = Mockery::mock(LedgerService::class);
        $this->app->instance(LedgerService::class, $ledgerServiceMock);

        // semantic_score が指定された場合、RagSearchServiceが呼ばれることを期待
        $ledgerServiceMock->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->id === $this->user->id),
                Mockery::on(fn ($arg) => $arg['order_by'] === 'semantic_score')
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $tool = new SearchLedgersTool($ledgerServiceMock);
        $request = new Request([
            'q' => '今日の業務内容について',
            'order_by' => 'semantic_score',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
    }

    #[Test]
    #[Group('semantic-search')]
    public function it_throws_an_error_when_semantic_search_is_called_without_a_query()
    {
        // Arrange
        $ledgerService = $this->app->make(LedgerService::class);
        $tool = new SearchLedgersTool($ledgerService);
        $request = new Request([
            'order_by' => 'semantic_score',
            // 'q' is intentionally omitted
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertTrue($response->isError());
        $this->assertStringContainsString('semantic_score sorting requires a search query (q parameter)', $response->content());
    }

    #[Group('semantic-search')]
    #[Test]
    public function it_does_not_perform_semantic_search_for_other_order_by_values()
    {
        // Arrange
        $ledgerServiceMock = Mockery::mock(LedgerService::class);
        $this->app->instance(LedgerService::class, $ledgerServiceMock);

        // semantic_score 以外の場合、通常の検索が呼ばれることを期待
        $ledgerServiceMock->shouldReceive('searchLedgersForApi')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg->id === $this->user->id),
                Mockery::on(fn ($arg) => $arg['order_by'] === 'composite_score')
            )
            ->andReturn([
                'ledgers' => collect([]),
                'meta' => [],
                'total' => 0,
            ]);

        $tool = new SearchLedgersTool($ledgerServiceMock);
        $request = new Request([
            'q' => 'テストクエリ',
            'order_by' => 'composite_score',
        ]);

        // Act
        $response = $tool->handle($request);

        // Assert
        $this->assertFalse($response->isError());
    }

    #[Test]
    #[Group('semantic-search')]
    public function it_finds_semantically_similar_ledger_even_if_keywords_do_not_match()
    {
        // 1. EmbeddingServiceのモック化 (外部API呼び出しを回避)
        // これによりテストの高速化と安定化を図る
        $embeddingServiceMock = Mockery::mock(\App\Services\EmbeddingService::class);
        // 1536次元のダミーベクトル (全て0.1)
        $dummyVector = array_fill(0, 1536, 0.1);

        $embeddingServiceMock->shouldReceive('embed')
            ->andReturnUsing(function ($texts) use ($dummyVector) {
                // 単一の文字列の場合は単一のベクトルを返す
                if (is_string($texts)) {
                    return $dummyVector;
                }

                // 配列の場合は入力テキスト数分のベクトルを返す
                return array_fill(0, count($texts), $dummyVector);
            });

        $this->app->instance(\App\Services\EmbeddingService::class, $embeddingServiceMock);

        // 2. テストデータの作成 (Seederを使わずFactoryで作成)
        // Adminユーザー
        $adminUser = User::factory()->create([
            'email' => 'admin-test@example.com',
        ]);
        $this->actingAs($adminUser);

        // フォルダー
        $folder = \App\Models\Folder::factory()->create(['title' => '日報']);

        // 権限設定: ユーザーが検索できるようにフォルダへのアクセス権を付与
        $role = \Spatie\Permission\Models\Role::create(['name' => 'test-admin', 'guard_name' => 'web']);
        $adminUser->assignRole($role);

        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::ADMIN,
            'creator_id' => $adminUser->id,
            'modifier_id' => $adminUser->id,
        ]);

        // カラム定義
        // IDを明示的に指定して、Ledgerのcontent作成時に参照できるようにする
        $columns = [
            ['id' => 1, 'name' => '日付', 'type' => 'YMD', 'key' => 'date', 'display_level' => 1, 'group' => '基本情報', 'order' => 1],
            ['id' => 2, 'name' => '顧客名', 'type' => 'text', 'key' => 'client', 'display_level' => 1, 'group' => '基本情報', 'order' => 2],
            ['id' => 3, 'name' => '訪問目的', 'type' => 'text', 'key' => 'purpose', 'display_level' => 2, 'group' => '詳細', 'order' => 3],
            ['id' => 4, 'name' => '商談ステータス', 'type' => 'select', 'key' => 'status', 'options' => ['提案中', '成約', '失注'], 'display_level' => 2, 'group' => '詳細', 'order' => 4],
            ['id' => 5, 'name' => '優先度', 'type' => 'select', 'key' => 'priority', 'options' => ['高', '中', '低'], 'display_level' => 2, 'group' => '詳細', 'order' => 5],
            ['id' => 6, 'name' => '商談内容', 'type' => 'textarea', 'key' => 'details', 'display_level' => 3, 'group' => '内容', 'order' => 6],
            ['id' => 7, 'name' => '成果・所感', 'type' => 'textarea', 'key' => 'results', 'display_level' => 3, 'group' => '内容', 'order' => 7],
            ['id' => 8, 'name' => '次回アクション', 'type' => 'textarea', 'key' => 'next_action', 'display_level' => 3, 'group' => '内容', 'order' => 8],
        ];

        $ledgerDefine = \App\Models\LedgerDefine::factory()->create([
            'title' => '[TEST] 営業日報',
            'folder_id' => $folder->id,
            'column_define' => $columns,
        ]);

        // Ledger作成
        // contentは column_id => value の形式
        $content = [
            1 => '2025-10-20',
            2 => 'セマンティック検索テスト株式会社',
            3 => '性能評価',
            4 => '提案中',
            5 => '高',
            6 => 'このプロジェクトでは、全社的な経費削減が最重要課題となっている。特に、出張費や交際費の見直しが急務である。',
            7 => 'コストカットの具体的な方法について、次回の会議で提案する必要がある。',
            8 => '経費削減案の資料を作成する。',
        ];

        $ledger = \App\Models\Ledger::factory()->create([
            'ledger_define_id' => $ledgerDefine->id,
            'content' => $content,
            'creator_id' => $adminUser->id,
            'tenant_id' => $this->getTenant()->id,
        ]);

        // テナントIDを確実に保存（Factoryで設定されない場合を考慮）
        if ($ledger->tenant_id !== $this->getTenant()->id) {
            $ledger->tenant_id = $this->getTenant()->id;
            $ledger->save();
        }

        // 3. ベクトル化 Job実行 (同期)
        // ここでMockされたEmbeddingServiceが呼ばれ、ダミーベクトルが保存される
        ProcessLedgerForRagJob::dispatchSync($ledger->id);

        // テナントを再初期化（念のため）
        tenancy()->initialize($this->getTenant());

        // チャンクが作成されたことを確認
        $chunkCount = \App\Models\LedgerChunk::where('ledger_id', $ledger->id)->count();
        $this->assertGreaterThan(0, $chunkCount,
            "No chunks were created for ledger {$ledger->id}. RAG processing may have failed.");

        // 4. MCPツールで検索
        // 類似度閾値を下げる（ダミーベクトル同士の距離計算になるため）
        // 同じベクトルなら類似度1.0になるはずだが、念のため0.0にしておく
        config(['rag.similarity_threshold' => 0.0]);

        $adminToken = $adminUser->createToken('admin-test-token')->plainTextToken;
        putenv('MCP_AUTH_TOKEN='.$adminToken);

        $tool = new SearchLedgersTool($this->app->make(LedgerService::class));
        $request = new Request([
            'q' => '費用を切り詰める方法', // キーワード一致しないクエリ
            'order_by' => 'semantic_score',
        ]);

        // Act
        $response = $tool->handle($request);
        $result = json_decode($response->content(), true);

        // Assert
        $this->assertFalse($response->isError(), "MCP tool returned an error: {$response->content()}");

        $this->assertGreaterThanOrEqual(1, count($result['ledgers']),
            'Expected at least 1 ledger, but found '.count($result['ledgers']).'. Result: '.json_encode($result));

        // 最初の結果が作成したledgerであることを確認
        $this->assertEquals($ledger->id, $result['ledgers'][0]['id'],
            'The found ledger ID does not match the created one.');
    }
}
