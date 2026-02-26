<?php

namespace Tests\Feature\Services\Scoring;

use App\Models\Ledger;
use App\Models\User;
use App\Services\Scoring\PopularityScoreService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * PopularityScoreService のテスト
 *
 * 直近30日間のユニーク閲覧者数を5倍してスコアを算出（最大100）。
 */
#[CoversClass(PopularityScoreService::class)]
class PopularityScoreServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private PopularityScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->service = new PopularityScoreService;
    }

    /** viewed アクティビティを直接作成するヘルパー */
    private function createViewActivity(Ledger $ledger, User $user, \Carbon\Carbon $createdAt): void
    {
        Activity::create([
            'log_name' => 'default',
            'description' => 'viewed',
            'subject_type' => Ledger::class,
            'subject_id' => $ledger->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'properties' => '{}',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    // ================================================================
    // calculate
    // ================================================================

    #[Test]
    public function calculate_returns_zero_when_no_views(): void
    {
        $ledger = Ledger::factory()->create();

        $score = $this->service->calculate($ledger);

        $this->assertEquals(0.0, $score);
    }

    #[Test]
    public function calculate_returns_five_points_per_unique_viewer(): void
    {
        $ledger = Ledger::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->createViewActivity($ledger, $user1, now()->subDays(5));
        $this->createViewActivity($ledger, $user2, now()->subDays(10));

        $score = $this->service->calculate($ledger);

        // 2ユーザー * 5点 = 10点
        $this->assertEquals(10.0, $score);
    }

    #[Test]
    public function calculate_counts_unique_viewers_not_total_views(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        // 同一ユーザーが3回閲覧
        $this->createViewActivity($ledger, $user, now()->subDays(1));
        $this->createViewActivity($ledger, $user, now()->subDays(2));
        $this->createViewActivity($ledger, $user, now()->subDays(3));

        $score = $this->service->calculate($ledger);

        // ユニーク1人 * 5点 = 5点
        $this->assertEquals(5.0, $score);
    }

    #[Test]
    public function calculate_ignores_views_older_than_30_days(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        // 30日以上前の閲覧
        $this->createViewActivity($ledger, $user, now()->subDays(45));

        $score = $this->service->calculate($ledger);

        $this->assertEquals(0.0, $score);
    }

    #[Test]
    public function calculate_caps_score_at_100(): void
    {
        $ledger = Ledger::factory()->create();

        // 21ユーザーが閲覧（21 * 5 = 105 → 100にキャップ）
        $users = User::factory()->count(21)->create();
        foreach ($users as $user) {
            $this->createViewActivity($ledger, $user, now()->subDays(1));
        }

        $score = $this->service->calculate($ledger);

        $this->assertEquals(100.0, $score);
    }

    #[Test]
    public function calculate_ignores_non_viewed_activities(): void
    {
        $ledger = Ledger::factory()->create();
        $user = User::factory()->create();

        // 'viewed' 以外のアクティビティ（スコアに含まれない）
        Activity::create([
            'log_name' => 'default',
            'description' => 'updated',
            'subject_type' => Ledger::class,
            'subject_id' => $ledger->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'properties' => '{}',
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        $score = $this->service->calculate($ledger);

        $this->assertEquals(0.0, $score);
    }
}
