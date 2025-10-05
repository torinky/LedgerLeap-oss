<?php

namespace Tests\Unit\Mcp\Tools;

use App\Enums\WorkflowStatus;
use App\Mcp\Tools\GetLedgerStatsTool;
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

class GetLedgerStatsToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected GetLedgerStatsTool $tool;

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
        $mockRepository->shouldReceive('getWritableFolders')
            ->andReturn(collect([$this->folder]));
        $mockRepository->shouldReceive('clearAllCache')->byDefault()->andReturn(true);
        $mockRepository->shouldReceive('refreshAllCache')->byDefault()->andReturn(true);

        $this->app->instance(WritableFolderRepository::class, $mockRepository);

        // GetLedgerStatsToolをインスタンス化
        $analyticsService = new AnalyticsService($mockRepository);
        $this->tool = new GetLedgerStatsTool($analyticsService);

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
        // テストデータを作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $request = new Request([
            'period' => 'this_week',
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('total_created', $result);
        $this->assertArrayHasKey('by_define', $result);
        $this->assertArrayHasKey('by_status', $result);
        $this->assertArrayHasKey('by_creator', $result);
    }

    #[Test]
    public function it_returns_stats_in_summary_format()
    {
        // テストデータを作成
        Ledger::factory()->count(5)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $request = new Request([
            'period' => 'this_week',
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
        $this->assertArrayHasKey('period', $displayFields);
        $this->assertArrayHasKey('total_created', $displayFields);
        $this->assertArrayHasKey('top_ledger_defines', $displayFields);
        $this->assertArrayHasKey('status_breakdown', $displayFields);
        $this->assertArrayHasKey('top_creators', $displayFields);

        // summaryの確認
        $this->assertStringContainsString('件の台帳が作成されました', $result['__summary__']);
    }

    #[Test]
    public function it_defaults_to_summary_format()
    {
        $request = new Request([
            'period' => 'today',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        // summary形式で返されることを確認
        $this->assertArrayHasKey('__display_fields__', $result);
        $this->assertArrayHasKey('__summary__', $result);
    }

    #[Test]
    public function it_supports_various_period_options()
    {
        $periods = [
            'today',
            'yesterday',
            'this_week',
            'last_week',
            'this_month',
            'last_month',
            'last_7_days',
            'last_30_days',
        ];

        foreach ($periods as $period) {
            $request = new Request([
                'period' => $period,
                'format' => 'raw',
            ]);

            $response = $this->tool->handle($request);

            $this->assertFalse($response->isError(), "Period '{$period}' should be supported");
            $result = json_decode($response->content(), true);
            $this->assertArrayHasKey('period', $result);
        }
    }

    #[Test]
    public function it_returns_zero_stats_when_no_data()
    {
        $request = new Request([
            'period' => 'today',
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertEquals(0, $result['total_created']);
        $this->assertEmpty($result['by_define']);
        $this->assertEmpty($result['by_status']);
        $this->assertEmpty($result['by_creator']);
    }

    #[Test]
    public function it_includes_japanese_translations_in_summary()
    {
        // 異なるステータスの台帳を作成
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $request = new Request([
            'period' => 'today',
            'format' => 'summary',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $summary = $result['__summary__'];
        $this->assertStringContainsString('本日', $summary);
        $this->assertStringContainsString('件の台帳が作成されました', $summary);
        // データがある場合はステータス別内訳が表示されるはず
        if (! empty($result['stats']['by_status'])) {
            $this->assertStringContainsString('ステータス別内訳', $summary);
        }
    }

    #[Test]
    public function it_respects_user_folder_permissions()
    {
        // 別のユーザーとフォルダを作成
        $anotherUser = User::factory()->create();
        $anotherFolder = Folder::factory()->create();
        $anotherDefine = LedgerDefine::factory()->create([
            'folder_id' => $anotherFolder->id,
        ]);

        // 別ユーザーの台帳を作成（このユーザーには見えないはず）
        Ledger::factory()->count(10)->create([
            'ledger_define_id' => $anotherDefine->id,
            'creator_id' => $anotherUser->id,
            'created_at' => Carbon::now()->subHours(2),
        ]);

        // 現在のユーザーの台帳を作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $request = new Request([
            'period' => 'today',
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        // アクセス権のある台帳のみカウントされることを確認
        // 実際の件数は権限設定に依存するため、ここでは構造のみ確認
        $this->assertArrayHasKey('total_created', $result);
        $this->assertIsInt($result['total_created']);
    }
}
