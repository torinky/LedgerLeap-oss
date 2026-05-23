<?php

namespace App\Services\Ledger;

use App\Models\Ledger;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LedgerDefineStatsService
{
    /**
     * キャッシュ有効期限（秒）
     */
    private const CACHE_TTL_SECONDS = 60;

    /**
     * 台帳定義ごとの全体統計を計算する
     *
     * @param  array<int>  $ledgerDefineIds  対象の台帳定義IDリスト
     * @param  string|null  $tenantId  テナントID
     * @param  User|null  $user  ユーザー（キャッシュ分離用）
     * @return array<int, array{count:int,avg_score:float,max_score:float,min_score:float,has_scores:bool}>
     */
    public function computeOverallStats(
        array $ledgerDefineIds,
        ?string $tenantId = null,
        ?User $user = null,
    ): array {
        if (empty($ledgerDefineIds)) {
            return [];
        }

        $statsComputeStartedAt = microtime(true);
        $cacheKey = $this->buildCacheKey($ledgerDefineIds, $tenantId, $user);

        $result = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($ledgerDefineIds) {
            $rows = Ledger::query()
                ->whereIn('ledger_define_id', $ledgerDefineIds)
                ->groupBy('ledger_define_id')
                ->selectRaw('
                    ledger_define_id,
                    COUNT(*) as count,
                    AVG(CASE WHEN composite_score > 0 THEN composite_score END) as avg_score,
                    MAX(composite_score) as max_score,
                    MIN(composite_score) as min_score,
                    SUM(CASE WHEN composite_score > 0 THEN 1 ELSE 0 END) as positive_score_count
                ')
                ->get()
                ->keyBy('ledger_define_id');

            return $rows->map(function ($row) {
                $hasScores = $row->positive_score_count > 0;

                return [
                    'count' => (int) $row->count,
                    'avg_score' => $hasScores ? round((float) $row->avg_score, 1) : 0,
                    'max_score' => $hasScores ? round((float) $row->max_score, 1) : 0,
                    'min_score' => $hasScores ? round((float) $row->min_score, 1) : 0,
                    'has_scores' => $hasScores,
                ];
            })->toArray();
        });

        $durationMs = (microtime(true) - $statsComputeStartedAt) * 1000;

        Log::debug('LedgerDefineStatsService::computeOverallStats', [
            'duration_ms' => round($durationMs, 3),
            'define_count' => count($ledgerDefineIds),
            'result_count' => count($result),
            'cache_key' => $cacheKey,
        ]);

        return $result;
    }

    /**
     * キャッシュキーを構築する
     */
    private function buildCacheKey(array $ledgerDefineIds, ?string $tenantId, ?User $user): string
    {
        sort($ledgerDefineIds);
        $defineIdsSignature = md5(implode(',', $ledgerDefineIds));
        $userId = $user?->id ?? 'guest';
        $tenant = $tenantId ?? (tenant()?->id ?? 'central');

        return "ledger_overall_stats:{$tenant}:{$userId}:{$defineIdsSignature}";
    }
}
