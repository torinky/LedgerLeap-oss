<?php

namespace App\Services\Scoring;

use Carbon\Carbon;

class FreshnessScoreService
{
    /**
     * Calculate the freshness score based on the last update time.
     *
     * 仕様: max(0, 100 - daysSinceUpdate * 2)
     * - 今日更新: 100点
     * - 10日前: 80点
     * - 30日前: 40点
     * - 50日以上前: 0点
     */
    public function calculate(Carbon $updatedAt): float
    {
        $daysSinceUpdate = (int) abs(now()->diffInDays($updatedAt));

        return (float) max(0, 100 - ($daysSinceUpdate * 2));
    }
}
