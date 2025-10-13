<?php

namespace App\Services\Scoring;

use App\Enums\WorkflowStatus;
use App\Models\Ledger;

class ImportanceScoreService
{
    /**
     * Calculate the importance score for a ledger based on workflow status.
     *
     * Phase 1では既存のワークフロー状態のみを使用し、
     * is_pinned や priority_level は使用しない（既存機能に存在しないため）。
     */
    public function calculate(Ledger $ledger): float
    {
        $score = match ($ledger->status) {
            WorkflowStatus::PENDING_APPROVAL => 30,    // 承認待ち（最優先）
            WorkflowStatus::PENDING_INSPECTION => 20,  // 点検待ち
            WorkflowStatus::DRAFT => 10,               // 下書き
            default => 0,                               // 通常
        };

        return (float) $score;
    }
}
