<?php

namespace App\Services\Scoring;

use App\Models\Ledger;
use App\Models\ScoringConfig;

class CompositeScoreCalculator
{
    public function __construct(
        private FreshnessScoreService $freshnessScoreService,
        private ImportanceScoreService $importanceScoreService,
        private PopularityScoreService $popularityScoreService
    ) {
    }

    /**
     * @param Ledger $ledger
     * @param ScoringConfig $config
     * @return array{composite_score: float, breakdown: array}
     */
    public function calculate(Ledger $ledger, ScoringConfig $config): array
    {
        // 各スコアサービスを呼び出し、スコアを取得
        $activityScore = (float) $ledger->activity_score;
        $freshnessScore = $this->freshnessScoreService->calculate($ledger->updated_at);
        $importanceScore = $this->importanceScoreService->calculate($ledger);
        $popularityScore = $this->popularityScoreService->calculate($ledger);
        $relevanceScore = 0; // バッチ処理では関連性スコアは0

        // 重み付けに基づいて複合スコアを計算
        $compositeScore =
            ($activityScore * $config->activity_weight) +
            ($freshnessScore * $config->freshness_weight) +
            ($importanceScore * $config->importance_weight) +
            ($popularityScore * $config->popularity_weight) +
            ($relevanceScore * $config->relevance_weight);

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