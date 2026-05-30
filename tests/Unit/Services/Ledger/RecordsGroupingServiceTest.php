<?php

namespace Tests\Unit\Services\Ledger;

use App\Services\Ledger\RecordsGroupingService;
use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(RecordsGroupingService::class)]
class RecordsGroupingServiceTest extends TestCase
{
    private RecordsGroupingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RecordsGroupingService;
    }

    // ----------------------------------------------------------------
    // Empty collections
    // ----------------------------------------------------------------

    #[Test]
    public function empty_collection_returns_empty_groups_and_stats(): void
    {
        $result = $this->service->groupAndComputeStats(collect(), false);

        $this->assertEmpty($result['groups']);
        $this->assertEmpty($result['stats']);
    }

    #[Test]
    public function empty_paginator_returns_empty_groups_and_stats(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10, 1);
        $result = $this->service->groupAndComputeStats($paginator, false);

        $this->assertEmpty($result['groups']);
        $this->assertEmpty($result['stats']);
    }

    // ----------------------------------------------------------------
    // Single ledger define ID
    // ----------------------------------------------------------------

    #[Test]
    public function single_define_groups_correctly(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(1, 20, 200),
            $this->makeLedger(1, 30, 300),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);

        $this->assertCount(1, $result['groups']);
        $this->assertCount(3, $result['groups'][1]);
        $this->assertSame(10, $result['groups'][1][0]->id);
    }

    #[Test]
    public function single_define_stats_are_correct(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(1, 20, 200),
            $this->makeLedger(1, 30, 300),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);
        $stats = $result['stats'][1];

        $this->assertSame(3, $stats['count']);
        $this->assertSame(200.0, $stats['avg_score']);
        $this->assertSame(300.0, $stats['max_score']);
        $this->assertSame(100.0, $stats['min_score']);
        $this->assertTrue($stats['has_scores']);
    }

    // ----------------------------------------------------------------
    // Multiple ledger define IDs
    // ----------------------------------------------------------------

    #[Test]
    public function multiple_defines_group_by_define_id(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(2, 20, 200),
            $this->makeLedger(1, 30, 300),
            $this->makeLedger(2, 40, 400),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);

        $this->assertCount(2, $result['groups']);
        $this->assertCount(2, $result['groups'][1]);
        $this->assertCount(2, $result['groups'][2]);
    }

    #[Test]
    public function multiple_defines_have_separate_stats(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(1, 20, 200),
            $this->makeLedger(2, 30, 50),
            $this->makeLedger(2, 40, 150),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);

        $this->assertSame(150.0, $result['stats'][1]['avg_score']);
        $this->assertSame(100.0, $result['stats'][2]['avg_score']);
    }

    // ----------------------------------------------------------------
    // Zero / negative scores handling
    // ----------------------------------------------------------------

    #[Test]
    public function zero_scores_are_excluded_from_stats(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(1, 20, 0),
            $this->makeLedger(1, 30, 0),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);
        $stats = $result['stats'][1];

        $this->assertSame(3, $stats['count']);
        $this->assertSame(100.0, $stats['avg_score']);
        $this->assertSame(100.0, $stats['max_score']);
        $this->assertSame(100.0, $stats['min_score']);
        $this->assertTrue($stats['has_scores']);
    }

    #[Test]
    public function all_zero_scores_result_in_zero_stats(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 0),
            $this->makeLedger(1, 20, 0),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);
        $stats = $result['stats'][1];

        $this->assertSame(2, $stats['count']);
        $this->assertSame(0, $stats['avg_score']);
        $this->assertSame(0, $stats['max_score']);
        $this->assertSame(0, $stats['min_score']);
        $this->assertFalse($stats['has_scores']);
    }

    // ----------------------------------------------------------------
    // Sorting behavior
    // ----------------------------------------------------------------

    #[Test]
    public function non_search_mode_sorts_by_define_id_ascending(): void
    {
        $records = collect([
            $this->makeLedger(3, 10, 100),
            $this->makeLedger(1, 20, 300),
            $this->makeLedger(2, 30, 200),
        ]);

        $result = $this->service->groupAndComputeStats($records, false);
        $keys = $result['groups']->keys()->all();

        $this->assertSame([1, 2, 3], $keys);
    }

    #[Test]
    public function search_mode_sorts_by_avg_score_descending(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(2, 20, 300),
            $this->makeLedger(3, 30, 200),
        ]);

        $result = $this->service->groupAndComputeStats($records, true);
        $keys = $result['groups']->keys()->all();

        $this->assertSame([2, 3, 1], $keys);
    }

    #[Test]
    public function search_mode_tie_breaker_keeps_stable_order(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(2, 20, 100),
            $this->makeLedger(3, 30, 100),
        ]);

        $result = $this->service->groupAndComputeStats($records, true);
        $keys = $result['groups']->keys()->all();

        // 同じavg_scoreの場合、元の出現順に近い順序を維持（CollectionのsortByDescの挙動）
        $this->assertSame([1, 2, 3], $keys);
    }

    // ----------------------------------------------------------------
    // Paginator support
    // ----------------------------------------------------------------

    #[Test]
    public function length_aware_paginator_is_handled_correctly(): void
    {
        $records = collect([
            $this->makeLedger(1, 10, 100),
            $this->makeLedger(2, 20, 200),
        ]);
        $paginator = new LengthAwarePaginator($records, 2, 10, 1);

        $result = $this->service->groupAndComputeStats($paginator, false);

        $this->assertCount(2, $result['groups']);
        $this->assertSame(100.0, $result['stats'][1]['avg_score']);
        $this->assertSame(200.0, $result['stats'][2]['avg_score']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeLedger(int $defineId, int $id, int $score): object
    {
        return (object) [
            'ledger_define_id' => $defineId,
            'id' => $id,
            'composite_score' => $score,
        ];
    }
}
