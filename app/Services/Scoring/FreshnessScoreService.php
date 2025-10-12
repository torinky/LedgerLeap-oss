<?php

namespace App\Services\Scoring;

use Carbon\Carbon;

class FreshnessScoreService
{
    /**
     * Calculate the freshness score based on the last update time.
     * The score decays over time using a logistic function.
     */
    public function calculate(Carbon $updatedAt): float
    {
        $hoursAgo = now()->diffInHours($updatedAt);

        // The formula is designed to give:
        // - ~100 points for 0 hours ago
        // - ~80 points for 24 hours ago
        // - ~50 points for 7 days (168 hours) ago
        // - ~20 points for 30 days ago
        // - ~5 points for 90 days ago
        $score = 100 / (1 + exp(($hoursAgo - 168) / 168));

        return round($score, 2);
    }
}
