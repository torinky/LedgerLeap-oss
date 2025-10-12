<?php

namespace App\Services\Scoring;

use App\Models\Ledger;
use App\Models\ScoringConfig;

class CompositeScoreCalculator
{
    /**
     * @param Ledger $ledger
     * @param ScoringConfig $config
     * @return array{composite_score: float, breakdown: array}
     */
    public function calculate(Ledger $ledger, ScoringConfig $config): array
    {
        // ToDo: 各スコアサービス（Freshness, Importance, Popularity）を呼び出し、
        //       正規化したスコアを取得するロジックを実装する

        $activityScore = $ledger->activity_score; // 活動スコアは事前計算済み
        $freshnessScore = 50.0; //仮
        $importanceScore = 50.0; //仮
        $popularityScore = 50.0; //仮
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
