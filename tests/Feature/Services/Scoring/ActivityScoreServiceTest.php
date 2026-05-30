<?php

namespace Tests\Feature\Services\Scoring;

use App\Models\Ledger;
use App\Services\Scoring\ActivityScoreService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

/**
 * ActivityScoreService のテスト
 *
 * calculateForLedger() と updateAllLedgers() を検証する。
 * Spatie\Activitylog を使用するため Feature テストが必要。
 *
 * 注意: Ledger は LogsActivity トレイトを持つため factory()->create() すると
 * activity_log に自動記録される。各テストはベースライン計測後に差分で検証する。
 */
#[CoversClass(ActivityScoreService::class)]
class ActivityScoreServiceTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    private ActivityScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
        $this->service = new ActivityScoreService;
    }

    /**
     * tenant_id を明示して Ledger を作成するヘルパー
     * BelongsToTenant スコープに乗るため tenant_id 設定が必要
     */
    private function makeLedger(): Ledger
    {
        return Ledger::factory()->create([
            'tenant_id' => tenancy()->tenant->id,
        ]);
    }

    /** Activity レコードを直接作成するヘルパー */
    private function createActivity(Ledger $ledger, string $description, Carbon $createdAt): void
    {
        Activity::create([
            'log_name' => 'default',
            'description' => $description,
            'subject_type' => Ledger::class,
            'subject_id' => $ledger->id,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => '{}',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    // ================================================================
    // calculateForLedger
    // ================================================================

    #[Test]
    public function calculate_for_ledger_returns_same_score_when_no_extra_activity(): void
    {
        $ledger = $this->makeLedger();
        // Ledger作成時に自動記録されたアクティビティを含むベースライン
        $baseline = $this->service->calculateForLedger($ledger);

        // 追加のアクティビティなし → スコア変化なし
        $score = $this->service->calculateForLedger($ledger);

        $this->assertEquals($baseline, $score);
    }

    #[Test]
    public function calculate_for_ledger_increments_by_weighted_activity(): void
    {
        $ledger = $this->makeLedger();
        $baseline = $this->service->calculateForLedger($ledger);

        // 直近7日以内2件 + 7〜30日前1件を追加
        $this->createActivity($ledger, 'test_event', now()->subDays(3));
        $this->createActivity($ledger, 'test_event', now()->subDays(5));
        $this->createActivity($ledger, 'test_event', now()->subDays(15));

        $score = $this->service->calculateForLedger($ledger);

        // 追加分: (2 * 10) + (1 * 3) = 23
        $this->assertEquals($baseline + 23, $score);
    }

    #[Test]
    public function calculate_for_ledger_ignores_activity_older_than_30_days(): void
    {
        $ledger = $this->makeLedger();
        $baseline = $this->service->calculateForLedger($ledger);

        // 30日超のアクティビティ（スコアに含まれない）
        $this->createActivity($ledger, 'test_event', now()->subDays(45));

        $score = $this->service->calculateForLedger($ledger);

        $this->assertEquals($baseline, $score);
    }

    #[Test]
    public function calculate_for_ledger_only_counts_activity_for_given_ledger(): void
    {
        $ledger1 = $this->makeLedger();
        $ledger2 = $this->makeLedger();

        $baseline = $this->service->calculateForLedger($ledger1);

        // ledger2 にのみアクティビティ追加
        $this->createActivity($ledger2, 'test_event', now()->subDays(1));

        $score = $this->service->calculateForLedger($ledger1);

        // ledger1 は変化なし
        $this->assertEquals($baseline, $score);
    }

    // ================================================================
    // updateAllLedgers
    // ================================================================

    #[Test]
    public function update_all_ledgers_returns_count_of_updated_ledgers(): void
    {
        $ledger1 = $this->makeLedger();
        $ledger2 = $this->makeLedger();
        $ledger3 = $this->makeLedger();

        $count = $this->service->updateAllLedgers();

        // テスト内で作ったLedger数以上を処理している
        $this->assertGreaterThanOrEqual(3, $count);
        $this->assertEquals(Ledger::count(), $count);
    }

    #[Test]
    public function update_all_ledgers_sets_activity_score_on_each_ledger(): void
    {
        $ledger = $this->makeLedger();
        // ベースラインを取得
        $baseline = $this->service->calculateForLedger($ledger);

        // 直近7日以内に追加1件
        $this->createActivity($ledger, 'test_event', now()->subDays(2));

        $this->service->updateAllLedgers();

        $ledger->refresh();
        // ベースライン + 10点が設定されている
        $this->assertEquals($baseline + 10, $ledger->activity_score);
    }

    #[Test]
    public function update_all_ledgers_does_not_update_timestamps(): void
    {
        $ledger = $this->makeLedger();
        $originalUpdatedAt = $ledger->updated_at;

        sleep(1);
        $this->service->updateAllLedgers();

        $ledger->refresh();
        $this->assertEquals(
            $originalUpdatedAt->toDateTimeString(),
            $ledger->updated_at->toDateTimeString()
        );
    }
}
