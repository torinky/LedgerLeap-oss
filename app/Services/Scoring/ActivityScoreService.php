<?php

namespace App\Services\Scoring;

use App\Models\Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Spatie\Activitylog\Models\Activity;

class ActivityScoreService
{
    private array $scoreConfig;
    private float $decayRate;

    public function __construct()
    {
        $this->scoreConfig = Config::get('ledgerleap.scoring.activity', []);
        $this->decayRate = Config::get('ledgerleap.scoring.decay.rate', 0.95);
    }

    /**
     * Calculate the activity score for a single ledger.
     *
     * @param Ledger $ledger
     * @return int
     */
    public function calculateForLedger(Ledger $ledger): int
    {
        $activities = Activity::query()
            ->where('subject_type', Ledger::class)
            ->where('subject_id', $ledger->id)
            ->get();

        $totalScore = 0;
        $now = Carbon::now();

        foreach ($activities as $activity) {
            $eventType = $activity->description;
            $baseScore = $this->scoreConfig[$eventType] ?? 0;

            if ($baseScore !== 0) {
                // Calculate the number of weeks passed since the activity occurred.
                $weeksPassed = $activity->created_at->diffInWeeks($now);

                // Apply decay to the score.
                $decayedScore = $baseScore * pow($this->decayRate, $weeksPassed);
                $totalScore += $decayedScore;
            }
        }

        return (int) round($totalScore);
    }

    /**
     * Decay scores for all ledgers.
     * This method should be called periodically by a scheduled command.
     */
    public function decayScores(): void
    {
        // ToDo: Implement logic to decay scores for all ledgers.
        // This might involve chunking results for performance.
    }

    /**
     * Calculate and update the aggregated activity score for a ledger define.
     *
     * @param \App\Models\LedgerDefine $ledgerDefine
     */
    public function calculateLedgerDefineScore(\App\Models\LedgerDefine $ledgerDefine): void
    {
        // ToDo: Implement logic to sum up scores from related ledgers.
    }
}
