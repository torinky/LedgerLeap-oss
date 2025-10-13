<?php

namespace App\Services\Scoring;

use App\Models\Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Spatie\Activitylog\Models\Activity;

/**
 * 活動スコア計算サービス（簡素化版）
 *
 * Phase 1: 期間別カウント方式
 * - 直近7日間のイベント数 × 10点
 * - 直近30日間のイベント数 × 3点
 * - すべてのイベントを均等に1件としてカウント
 */
class ActivityScoreService
{
    private array $windows;

    public function __construct()
    {
        $this->windows = Config::get('ledgerleap.scoring.activity.windows', [
            ['days' => 7, 'multiplier' => 10],
            ['days' => 30, 'multiplier' => 3],
        ]);
    }

    /**
     * Calculate the activity score for a single ledger.
     *
     * 直近7日間と7-30日間を別々にカウント
     */
    public function calculateForLedger(Ledger $ledger): int
    {
        $now = Carbon::now();

        // 直近7日間のイベント数
        $last7days = Activity::query()
            ->where('subject_type', Ledger::class)
            ->where('subject_id', $ledger->id)
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->count();

        // 7-30日間のイベント数
        $last30days = Activity::query()
            ->where('subject_type', Ledger::class)
            ->where('subject_id', $ledger->id)
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->where('created_at', '<', $now->copy()->subDays(7))
            ->count();

        return ($last7days * 10) + ($last30days * 3);
    }

    /**
     * Calculate activity score for all ledgers and update database.
     *
     * @return int Number of ledgers updated
     */
    public function updateAllLedgers(): int
    {
        $chunkSize = Config::get('ledgerleap.scoring.batch.chunk_size', 100);
        $updatedCount = 0;

        Ledger::query()->chunk($chunkSize, function ($ledgers) use (&$updatedCount) {
            foreach ($ledgers as $ledger) {
                $score = $this->calculateForLedger($ledger);
                $ledger->activity_score = $score;
                // saveQuietly()を使用してアクティビティログを記録しない
                $ledger->saveQuietly();
                $updatedCount++;
            }
        });

        return $updatedCount;
    }
}
