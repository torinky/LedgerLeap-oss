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
     * 返り値:
     * - composite_score: float  複合スコア
     * - activity_score:  float  活動スコア
     * - freshness_score: float  新鮮度スコア
     * - importance_score: float 重要度スコア
     * - popularity_score: float 人気度スコア
     * - relevance_score:  float 関連性スコア（現在は常に0）
     *
     * @return array{composite_score: float, activity_score: float, freshness_score: float, importance_score: float, popularity_score: float, relevance_score: float}
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
        $relevanceScore = 0.0; // バッチ処理では関連性スコアは0

        // 重み付けに基づいて複合スコアを計算
        $compositeScore =
            ($activityScore * $weights['activity']) +
            ($freshnessScore * $weights['freshness']) +
            ($importanceScore * $weights['importance']) +
            ($popularityScore * $weights['popularity']) +
            ($relevanceScore * $weights['relevance']);

        return [
            'composite_score' => $compositeScore,
            'activity_score' => $activityScore,
            'freshness_score' => $freshnessScore,
            'importance_score' => $importanceScore,
            'popularity_score' => $popularityScore,
            'relevance_score' => $relevanceScore,
        ];
    }
}
