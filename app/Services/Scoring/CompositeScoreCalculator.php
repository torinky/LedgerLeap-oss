<?php

namespace App\Services\Scoring;

use App\Models\Ledger;
use Illuminate\Support\Facades\Config;

/**
 * 複合スコア計算サービス
 *
 * 複数のスコアを重み付けして最終的な複合スコアを算出
 */
class CompositeScoreCalculator
{
    public function __construct(
        private FreshnessScoreService $freshnessScoreService,
        private ImportanceScoreService $importanceScoreService,
        private PopularityScoreService $popularityScoreService
    ) {}

    /**
     * Calculate composite score for a ledger using configured weights.
     *
     * @return array{composite_score: float, breakdown: array}
     */
    public function calculate(Ledger $ledger): array
    {
        // config/ledgerleap.php から重み付けを取得
        $weights = Config::get('ledgerleap.scoring.weights', [
            'activity' => 0.40,
            'freshness' => 0.30,
            'importance' => 0.30,
            'relevance' => 0.00,
            'popularity' => 0.00,
        ]);

        // 各スコアサービスを呼び出し、スコアを取得
        $activityScore = (float) $ledger->activity_score;
        $freshnessScore = $this->freshnessScoreService->calculate($ledger->updated_at);
        $importanceScore = $this->importanceScoreService->calculate($ledger);
        $popularityScore = $this->popularityScoreService->calculate($ledger);
        $relevanceScore = 0; // バッチ処理では関連性スコアは0

        // 重み付けに基づいて複合スコアを計算
        $compositeScore =
            ($activityScore * $weights['activity']) +
            ($freshnessScore * $weights['freshness']) +
            ($importanceScore * $weights['importance']) +
            ($popularityScore * $weights['popularity']) +
            ($relevanceScore * $weights['relevance']);

        return [
            'composite_score' => $compositeScore,
            'breakdown' => [
                'activity' => $activityScore,
                'freshness' => $freshnessScore,
                'importance' => $importanceScore,
                'popularity' => $popularityScore,
                'relevance' => $relevanceScore,
            ],
        ];
    }
}
