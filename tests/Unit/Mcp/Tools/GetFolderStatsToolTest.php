<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetFolderStatsTool;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Laravel\Mcp\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class GetFolderStatsToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected GetFolderStatsTool $tool;

    protected User $user;

    protected PersonalAccessToken $accessToken;

    protected Folder $folder;

    protected LedgerDefine $define;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // ユーザーとトークンを作成
        $this->user = User::factory()->create();
        $newAccessToken = $this->user->createToken('test-token');
        $this->accessToken = $newAccessToken->accessToken;

        // テストデータを作成
        $this->folder = Folder::factory()->create();
        $this->define = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
        ]);

        // WritableFolderRepositoryをモック
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->andReturn([$this->folder->id]);
        $mockRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
        $mockRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        // GetFolderStatsToolをインスタンス化
        $analyticsService = new AnalyticsService($mockRepository);
        $this->tool = new GetFolderStatsTool($analyticsService);

        // MCP_AUTH_TOKEN環境変数を設定
        putenv('MCP_AUTH_TOKEN='.$newAccessToken->plainTextToken);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        putenv('MCP_AUTH_TOKEN');
    }

    #[Test]
    public function it_returns_stats_in_raw_format()
    {
        // テスト用の台帳を作成
        Ledger::factory()->count(5)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        $request = new Request([
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('folders', $result);
        $this->assertArrayHasKey('total_folders', $result);
        $this->assertArrayHasKey('total_ledger_defines', $result);
        $this->assertArrayHasKey('total_ledgers', $result);

        $this->assertEquals(1, $result['total_folders']);
        $this->assertEquals(1, $result['total_ledger_defines']);
        $this->assertEquals(5, $result['total_ledgers']);
    }

    #[Test]
    public function it_returns_stats_in_summary_format()
    {
        // テスト用の台帳を作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        $request = new Request([
            'format' => 'summary',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('__display_fields__', $result);
        $this->assertArrayHasKey('__summary__', $result);
        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('meta', $result);

        // display_fieldsの確認
        $displayFields = $result['__display_fields__'];
        $this->assertArrayHasKey('total_folders', $displayFields);
        $this->assertArrayHasKey('total_ledger_defines', $displayFields);
        $this->assertArrayHasKey('total_ledgers', $displayFields);
        $this->assertArrayHasKey('top_folders', $displayFields);
    }

    #[Test]
    public function it_defaults_to_summary_format()
    {
        $request = new Request([]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        // summary形式で返されることを確認
        $this->assertArrayHasKey('__display_fields__', $result);
        $this->assertArrayHasKey('__summary__', $result);
    }

    #[Test]
    public function it_includes_recent_activity_stats()
    {
        // 過去の台帳を作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // 最近の台帳を作成
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $request = new Request([
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertEquals(5, $result['total_ledgers']);
        $folderStat = $result['folders'][0];
        $this->assertEquals(2, $folderStat['recent_activity']);
    }

    #[Test]
    public function it_includes_japanese_translations_in_summary()
    {
        // テスト用の台帳を作成
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        $request = new Request([
            'format' => 'summary',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $summary = $result['__summary__'];
        $this->assertStringContainsString('アクセス可能なフォルダ', $summary);
        $this->assertStringContainsString('台帳定義', $summary);
        $this->assertStringContainsString('台帳', $summary);
    }

    #[Test]
    public function it_handles_multiple_folders()
    {
        // 2つ目のフォルダと台帳定義を作成
        $folder2 = Folder::factory()->create();
        $define2 = LedgerDefine::factory()->create([
            'folder_id' => $folder2->id,
        ]);

        // モックを更新して両方のフォルダを返すように
        $mockRepository = Mockery::mock(WritableFolderRepository::class);
        $mockRepository->shouldReceive('getReadableFolderIds')
            ->andReturn([$this->folder->id, $folder2->id]);
        $mockRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
        $mockRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        // ツールを再作成
        $analyticsService = new AnalyticsService($mockRepository);
        $this->tool = new GetFolderStatsTool($analyticsService);

        // 各フォルダに台帳を作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        Ledger::factory()->count(5)->create([
            'ledger_define_id' => $define2->id,
            'creator_id' => $this->user->id,
        ]);

        $request = new Request([
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertEquals(2, $result['total_folders']);
        $this->assertEquals(2, $result['total_ledger_defines']);
        $this->assertEquals(8, $result['total_ledgers']);
    }
}
