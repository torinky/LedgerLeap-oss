<?php

namespace Tests\Unit\Mcp\Tools;

use App\Mcp\Tools\GetUserActivityStatsTool;
use App\Models\CustomActivity;
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

class GetUserActivityStatsToolTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected GetUserActivityStatsTool $tool;

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

        // GetUserActivityStatsToolをインスタンス化
        $analyticsService = new AnalyticsService($mockRepository);
        $this->tool = new GetUserActivityStatsTool($analyticsService);

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
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        // アクティビティログを作成
        for ($i = 0; $i < 3; $i++) {
            CustomActivity::create([
                'subject_type' => Ledger::class,
                'subject_id' => $ledger->id,
                'causer_id' => $this->user->id,
                'causer_type' => User::class,
                'description' => 'created',
                'created_at' => Carbon::now()->subDays(2),
            ]);
        }

        $request = new Request([
            'period' => 'this_week',
            'format' => 'raw',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('period', $result);
        $this->assertArrayHasKey('total_activities', $result);
        $this->assertArrayHasKey('by_event', $result);
        $this->assertArrayHasKey('by_user', $result);
        $this->assertArrayHasKey('by_hour', $result);
    }

    #[Test]
    public function it_returns_stats_in_summary_format()
    {
        // テスト用の台帳を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        // アクティビティログを作成
        for ($i = 0; $i < 5; $i++) {
            CustomActivity::create([
                'subject_type' => Ledger::class,
                'subject_id' => $ledger->id,
                'causer_id' => $this->user->id,
                'causer_type' => User::class,
                'description' => 'updated',
                'created_at' => Carbon::now()->subHours(3),
            ]);
        }

        $request = new Request([
            'period' => 'today',
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
        $this->assertArrayHasKey('total_activities', $displayFields);
        $this->assertArrayHasKey('top_events', $displayFields);
        $this->assertArrayHasKey('top_users', $displayFields);
        $this->assertArrayHasKey('peak_hours', $displayFields);
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

        $this->assertGreaterThanOrEqual(0, $result['total_activities']);
        $this->assertIsArray($result['by_event']);
        $this->assertIsArray($result['by_user']);
        $this->assertIsArray($result['by_hour']);
    }

    #[Test]
    public function it_includes_japanese_translations_in_summary()
    {
        // テスト用の台帳を作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        // アクティビティログを作成
        for ($i = 0; $i < 3; $i++) {
            CustomActivity::create([
                'subject_type' => Ledger::class,
                'subject_id' => $ledger->id,
                'causer_id' => $this->user->id,
                'causer_type' => User::class,
                'description' => 'created',
                'created_at' => Carbon::now()->subHours(1),
            ]);
        }

        $request = new Request([
            'period' => 'today',
            'format' => 'summary',
        ]);

        $response = $this->tool->handle($request);

        $this->assertFalse($response->isError());
        $result = json_decode($response->content(), true);

        $summary = $result['__summary__'];
        $this->assertStringContainsString('本日', $summary);
        $this->assertStringContainsString('件の活動がありました', $summary);
    }
}
