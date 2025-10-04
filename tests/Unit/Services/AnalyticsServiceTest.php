<?php

namespace Tests\Unit\Services;

use App\Enums\WorkflowStatus;
use App\Models\CustomActivity;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Repositories\WritableFolderRepositoryInterface;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected AnalyticsService $service;

    protected User $user;

    protected Folder $folder;

    protected LedgerDefine $define;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();

        // テストデータの作成
        $this->user = User::factory()->create();
        $this->folder = Folder::factory()->create();
        $this->define = LedgerDefine::factory()->create([
            'folder_id' => $this->folder->id,
        ]);

        // WritableFolderRepositoryをモック
        $folderRepository = Mockery::mock(WritableFolderRepositoryInterface::class);
        $folderRepository->shouldReceive('getWritableFolders')
            ->with($this->user)
            ->andReturn(collect([$this->folder]));

        $this->service = new AnalyticsService($folderRepository);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    #[Test]
    public function it_returns_ledger_stats_by_period()
    {
        // テストデータの作成
        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        // 期間内の台帳を作成
        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        // 期間外の台帳を作成（統計に含まれないはず）
        Ledger::factory()->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // 統計を取得
        $stats = $this->service->getLedgerStatsByPeriod($this->user, $from, $to);

        // アサーション
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('total_created', $stats);
        $this->assertArrayHasKey('by_define', $stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_creator', $stats);

        $this->assertEquals(3, $stats['total_created']);
        $this->assertCount(1, $stats['by_define']);
        $this->assertEquals($this->define->id, $stats['by_define'][0]['ledger_define_id']);
        $this->assertEquals(3, $stats['by_define'][0]['count']);
    }

    #[Test]
    public function it_returns_empty_stats_when_no_accessible_folders()
    {
        // アクセス可能なフォルダがない場合のモックを作成
        $folderRepository = Mockery::mock(WritableFolderRepositoryInterface::class);
        $folderRepository->shouldReceive('getWritableFolders')
            ->with($this->user)
            ->andReturn(collect([]));

        $service = new AnalyticsService($folderRepository);

        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        $stats = $service->getLedgerStatsByPeriod($this->user, $from, $to);

        $this->assertEquals(0, $stats['total_created']);
        $this->assertEmpty($stats['by_define']);
        $this->assertEmpty($stats['by_status']);
        $this->assertEmpty($stats['by_creator']);
    }

    #[Test]
    public function it_groups_ledgers_by_status()
    {
        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        // 異なるステータスの台帳を作成
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::DRAFT,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        Ledger::factory()->count(3)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'status' => WorkflowStatus::APPROVED,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $stats = $this->service->getLedgerStatsByPeriod($this->user, $from, $to);

        $this->assertEquals(5, $stats['total_created']);
        $this->assertCount(2, $stats['by_status']);

        // ステータスごとの件数を確認
        $draftCount = collect($stats['by_status'])
            ->firstWhere('status', WorkflowStatus::DRAFT->value)['count'];
        $approvedCount = collect($stats['by_status'])
            ->firstWhere('status', WorkflowStatus::APPROVED->value)['count'];

        $this->assertEquals(2, $draftCount);
        $this->assertEquals(3, $approvedCount);
    }

    #[Test]
    public function it_returns_user_activity_stats()
    {
        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

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
                'description' => 'created',
                'created_at' => Carbon::now()->subDays(3),
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            CustomActivity::create([
                'subject_type' => Ledger::class,
                'subject_id' => $ledger->id,
                'causer_id' => $this->user->id,
                'causer_type' => User::class,
                'description' => 'updated',
                'created_at' => Carbon::now()->subDays(2),
            ]);
        }

        $stats = $this->service->getUserActivityStats($this->user, $from, $to);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('period', $stats);
        $this->assertArrayHasKey('total_activities', $stats);
        $this->assertArrayHasKey('by_event', $stats);
        $this->assertArrayHasKey('by_user', $stats);
        $this->assertArrayHasKey('by_hour', $stats);

        // 台帳作成時に自動的に1件のアクティビティが記録されるため、合計は9件
        $this->assertGreaterThanOrEqual(8, $stats['total_activities']);
        $this->assertGreaterThanOrEqual(2, count($stats['by_event']));
    }

    #[Test]
    public function it_returns_folder_stats()
    {
        // 台帳を作成（過去7日よりも前）
        Ledger::factory()->count(5)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // 最近の台帳を作成（過去7日以内）
        Ledger::factory()->count(2)->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $stats = $this->service->getFolderStats($this->user);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('folders', $stats);
        $this->assertArrayHasKey('total_folders', $stats);
        $this->assertArrayHasKey('total_ledger_defines', $stats);
        $this->assertArrayHasKey('total_ledgers', $stats);

        $this->assertEquals(1, $stats['total_folders']);
        $this->assertEquals(1, $stats['total_ledger_defines']);
        $this->assertEquals(7, $stats['total_ledgers']);

        // フォルダの詳細を確認
        $folderStat = $stats['folders'][0];
        $this->assertEquals($this->folder->id, $folderStat['folder_id']);
        $this->assertEquals(1, $folderStat['ledger_define_count']);
        $this->assertEquals(7, $folderStat['ledger_count']);
        $this->assertEquals(2, $folderStat['recent_activity']);
    }

    #[Test]
    public function it_limits_creator_stats_to_top_five()
    {
        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        // 6人のユーザーを作成
        for ($i = 0; $i < 6; $i++) {
            $user = User::factory()->create();
            // 各ユーザーが異なる数の台帳を作成（6件、5件、4件...1件）
            Ledger::factory()->count(6 - $i)->create([
                'ledger_define_id' => $this->define->id,
                'creator_id' => $user->id,
                'created_at' => Carbon::now()->subDays(3),
            ]);
        }

        $stats = $this->service->getLedgerStatsByPeriod($this->user, $from, $to);

        // 上位5名のみ取得されることを確認
        $this->assertCount(5, $stats['by_creator']);

        // 降順でソートされていることを確認
        $counts = array_column($stats['by_creator'], 'count');
        $this->assertEquals([6, 5, 4, 3, 2], $counts);
    }

    #[Test]
    public function it_limits_user_activity_stats_to_top_ten()
    {
        $from = Carbon::now()->subDays(7);
        $to = Carbon::now();

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->define->id,
            'creator_id' => $this->user->id,
        ]);

        // 11人のユーザーを作成し、それぞれが異なる数のアクティビティを生成
        for ($i = 0; $i < 11; $i++) {
            $user = User::factory()->create();
            for ($j = 0; $j < (11 - $i); $j++) {
                CustomActivity::create([
                    'subject_type' => Ledger::class,
                    'subject_id' => $ledger->id,
                    'causer_id' => $user->id,
                    'causer_type' => User::class,
                    'description' => 'created',
                    'created_at' => Carbon::now()->subDays(3),
                ]);
            }
        }

        $stats = $this->service->getUserActivityStats($this->user, $from, $to);

        // 上位10名のみ取得されることを確認
        $this->assertCount(10, $stats['by_user']);

        // 降順でソートされていることを確認
        $firstUserCount = $stats['by_user'][0]['count'];
        $lastUserCount = $stats['by_user'][9]['count'];
        $this->assertGreaterThanOrEqual($lastUserCount, $firstUserCount);
    }
}
