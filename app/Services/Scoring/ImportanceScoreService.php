<?php

namespace App\Services\Scoring;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;

class ImportanceScoreService
{
    /**
     * Calculate the importance score for a ledger based on workflow status.
     *
     * 仕様（docs/features/scoring-system.md より）:
     * - 承認待ち (PENDING_APPROVAL): 100点
     * - 点検待ち (PENDING_INSPECTION): 60点
     * - 下書き (DRAFT): 20点
     * - 承認済み (APPROVED): 10点
     * - その他 (NONE等): 0点
     */
    public function calculate(Ledger $ledger): float
    {
        $score = match ($ledger->status) {
            WorkflowStatus::PENDING_APPROVAL => 100,   // 承認待ち（最優先）
            WorkflowStatus::PENDING_INSPECTION => 60,   // 点検待ち
            WorkflowStatus::DRAFT => 20,   // 下書き
            WorkflowStatus::APPROVED => 10,   // 承認済み
            default => 0,    // NONE など
        };

        return (float) $score;
    }
}
