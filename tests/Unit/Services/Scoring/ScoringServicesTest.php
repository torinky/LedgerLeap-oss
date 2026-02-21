<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;
use App\Services\Scoring\ActivityScoreService;
use App\Services\Scoring\CompositeScoreCalculator;
use App\Services\Scoring\FreshnessScoreService;
use App\Services\Scoring\ImportanceScoreService;
use App\Services\Scoring\PopularityScoreService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(FreshnessScoreService::class)]
#[CoversClass(ImportanceScoreService::class)]
#[CoversClass(CompositeScoreCalculator::class)]
#[CoversClass(ActivityScoreService::class)]
#[CoversClass(PopularityScoreService::class)]
class ScoringServicesTest extends TestCase
{
    // ----------------------------------------------------------------
    // FreshnessScoreService
    // 仕様: max(0, 100 - daysSinceUpdate * 2)
    // ----------------------------------------------------------------

    public function test_freshness_score_today(): void
    {
        $service = new FreshnessScoreService;
        $score = $service->calculate(Carbon::now());
        // 0日前: 100 - (0 * 2) = 100
        $this->assertEquals(100.0, $score);
    }

    public function test_freshness_score_ten_days_ago(): void
    {
        $service = new FreshnessScoreService;
        $score = $service->calculate(Carbon::now()->subDays(10));
        // 10日前: 100 - (10 * 2) = 80
        $this->assertEquals(80.0, $score);
    }

    public function test_freshness_score_thirty_days_ago(): void
    {
        $service = new FreshnessScoreService;
        $score = $service->calculate(Carbon::now()->subDays(30));
        // 30日前: 100 - (30 * 2) = 40
        $this->assertEquals(40.0, $score);
    }

    public function test_freshness_score_fifty_days_ago_is_zero(): void
    {
        $service = new FreshnessScoreService;
        $score = $service->calculate(Carbon::now()->subDays(50));
        // 50日前: 100 - (50 * 2) = 0
        $this->assertEquals(0.0, $score);
    }

    public function test_freshness_score_over_fifty_days_does_not_go_negative(): void
    {
        $service = new FreshnessScoreService;
        $score = $service->calculate(Carbon::now()->subDays(90));
        // 90日前: max(0, 100 - 180) = 0（負にならない）
        $this->assertEquals(0.0, $score);
    }

    // ----------------------------------------------------------------
    // ImportanceScoreService
    // 仕様: PENDING_APPROVAL=100, PENDING_INSPECTION=60, DRAFT=20, APPROVED=10, default=0
    // ----------------------------------------------------------------

    public function test_importance_score_pending_approval(): void
    {
        $service = new ImportanceScoreService;
        $ledger = new Ledger;
        $ledger->status = WorkflowStatus::PENDING_APPROVAL;

        $score = $service->calculate($ledger);

        $this->assertEquals(100.0, $score);
    }

    public function test_importance_score_pending_inspection(): void
    {
        $service = new ImportanceScoreService;
        $ledger = new Ledger;
        $ledger->status = WorkflowStatus::PENDING_INSPECTION;

        $score = $service->calculate($ledger);

        $this->assertEquals(60.0, $score);
    }

    public function test_importance_score_draft(): void
    {
        $service = new ImportanceScoreService;
        $ledger = new Ledger;
        $ledger->status = WorkflowStatus::DRAFT;

        $score = $service->calculate($ledger);

        $this->assertEquals(20.0, $score);
    }

    public function test_importance_score_approved(): void
    {
        $service = new ImportanceScoreService;
        $ledger = new Ledger;
        $ledger->status = WorkflowStatus::APPROVED;

        $score = $service->calculate($ledger);

        $this->assertEquals(10.0, $score);
    }

    public function test_importance_score_none_is_zero(): void
    {
        $service = new ImportanceScoreService;
        $ledger = new Ledger;
        $ledger->status = WorkflowStatus::NONE;

        $score = $service->calculate($ledger);

        $this->assertEquals(0.0, $score);
    }

    // ----------------------------------------------------------------
    // CompositeScoreCalculator
    // ----------------------------------------------------------------

    public function test_composite_score_returns_all_expected_keys(): void
    {
        $freshnessService = Mockery::mock(FreshnessScoreService::class);
        $importanceService = Mockery::mock(ImportanceScoreService::class);
        $popularityService = Mockery::mock(PopularityScoreService::class);

        $ledger = new Ledger;
        $ledger->activity_score = 50;
        $ledger->updated_at = Carbon::now()->subHours(1);
        $ledger->status = WorkflowStatus::NONE;

        $freshnessService->shouldReceive('calculate')->once()->andReturn(80.0);
        $importanceService->shouldReceive('calculate')->once()->andReturn(10.0);
        $popularityService->shouldReceive('calculate')->once()->andReturn(5.0);

        Config::set('ledgerleap.scoring.weights', [
            'activity' => 0.40,
            'freshness' => 0.30,
            'importance' => 0.30,
            'relevance' => 0.00,
            'popularity' => 0.00,
        ]);

        $calculator = new CompositeScoreCalculator($freshnessService, $importanceService, $popularityService);
        $result = $calculator->calculate($ledger);

        // 必須キーが存在する
        $this->assertArrayHasKey('composite_score', $result);
        $this->assertArrayHasKey('activity_score', $result);
        $this->assertArrayHasKey('freshness_score', $result);
        $this->assertArrayHasKey('importance_score', $result);
        $this->assertArrayHasKey('popularity_score', $result);
        $this->assertArrayHasKey('relevance_score', $result);

        // composite = (50 * 0.4) + (80 * 0.3) + (10 * 0.3) + (5 * 0) + (0 * 0) = 20 + 24 + 3 = 47
        $this->assertEqualsWithDelta(47.0, $result['composite_score'], 0.01);
        $this->assertEquals(50.0, $result['activity_score']);
        $this->assertEquals(80.0, $result['freshness_score']);
        $this->assertEquals(10.0, $result['importance_score']);
        $this->assertEquals(5.0, $result['popularity_score']);
        $this->assertEquals(0.0, $result['relevance_score']);
    }

    public function test_composite_score_uses_default_weights_when_config_missing(): void
    {
        $freshnessService = Mockery::mock(FreshnessScoreService::class);
        $importanceService = Mockery::mock(ImportanceScoreService::class);
        $popularityService = Mockery::mock(PopularityScoreService::class);

        $ledger = new Ledger;
        $ledger->activity_score = 0;
        $ledger->updated_at = Carbon::now();
        $ledger->status = WorkflowStatus::NONE;

        $freshnessService->shouldReceive('calculate')->once()->andReturn(0.0);
        $importanceService->shouldReceive('calculate')->once()->andReturn(0.0);
        $popularityService->shouldReceive('calculate')->once()->andReturn(0.0);

        $calculator = new CompositeScoreCalculator($freshnessService, $importanceService, $popularityService);
        $result = $calculator->calculate($ledger);

        $this->assertEqualsWithDelta(0.0, $result['composite_score'], 0.01);
    }
}

