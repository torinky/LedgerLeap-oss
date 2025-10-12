<?php

namespace App\Services\Scoring;

use App\Models\Ledger;
use Spatie\Activitylog\Models\Activity;

class PopularityScoreService
{
    /**
     * Calculate the popularity score for a ledger.
     *
     * @param Ledger $ledger
     * @return float
     */
    public function calculate(Ledger $ledger): float
    {
        // Count unique viewers in the last 30 days
        $uniqueViewersCount = Activity::query()
            ->where('subject_type', Ledger::class)
            ->where('subject_id', $ledger->id)
            ->where('description', 'viewed')
            ->where('created_at', '>=', now()->subDays(30))
            ->distinct('causer_id')
            ->count('causer_id');

        // Calculate score (5 points per unique viewer, max 100)
        $score = min(100.0, $uniqueViewersCount * 5.0);

        return $score;
    }
}
