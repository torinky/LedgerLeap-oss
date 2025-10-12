<?php

namespace App\Services\Scoring;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;

class ImportanceScoreService
{
    /**
     * Calculate the importance score for a ledger.
     *
     * @param Ledger $ledger
     * @return float
     */
    public function calculate(Ledger $ledger): float
    {
        $score = 0;

        // Pinned status
        if ($ledger->is_pinned) {
            $score += 50;
        }

        // Priority level (0-2)
        $score += ($ledger->priority_level ?? 0) * 20;

        // Workflow status (pending approval)
        if ($ledger->status === WorkflowStatus::PENDING_APPROVAL) {
            $score += 20;
        }

        // Attachments
        if ($ledger->attached_files_count > 0) { // Assuming attached_files_count is available
            $score += 10;
        }

        // Number of tags (max 10 points)
        $score += min(($ledger->tags_count ?? 0) * 2, 10);

        // Clip the score to a maximum of 100
        return min($score, 100.0);
    }
}
