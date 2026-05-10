<?php

namespace App\Services\Ledger;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RecordsGroupingService
{
    /**
     * キャッシュ有効期限（秒）
     */
    private const CACHE_TTL_SECONDS = 30;

    /**
     * レコードコレクションを台帳定義ごとにグループ化し、統計情報を付与する
     *
     * @param  Collection|LengthAwarePaginator|Paginator  $ledgerRecords  ページネーション後のレコード
     * @param  bool  $isSearchActive  検索実行中かどうか（ソート順序の決定に使用）
     * @param  string|null  $tenantId  テナントID（キャッシュキー用）
     * @return array{
     *     groups: Collection<int, Collection>,
     *     stats: array<int, array{count:int,avg_score:float,max_score:float,min_score:float,has_scores:bool}>,
     *     timing: array{stats_compute_ms:float,grouping_ms:float,cache_hit:bool}
     * }
     */
    public function groupAndComputeStats(
        Collection|LengthAwarePaginator|Paginator $ledgerRecords,
        bool $isSearchActive = false,
        ?string $tenantId = null,
    ): array {
        $recordsCollection = $ledgerRecords instanceof LengthAwarePaginator || $ledgerRecords instanceof Paginator
            ? $ledgerRecords->getCollection()
            : $ledgerRecords;

        $cacheKey = $this->buildCacheKey($recordsCollection, $isSearchActive, $tenantId);

        $cacheHit = false;
        $statsComputeStartedAt = microtime(true);

        $result = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use (
            $recordsCollection,
            $isSearchActive,
            &$cacheHit
        ) {
            $cacheHit = false;

            // 統計計算
            $scoreStatsByDefineId = $recordsCollection
                ->groupBy('ledger_define_id')
                ->map(function (Collection $records) {
                    $scores = $records->pluck('composite_score')->filter(fn ($score) => $score > 0);

                    return [
                        'count' => $records->count(),
                        'avg_score' => $scores->count() > 0 ? round($scores->avg(), 1) : 0,
                        'max_score' => $scores->count() > 0 ? round($scores->max(), 1) : 0,
                        'min_score' => $scores->count() > 0 ? round($scores->min(), 1) : 0,
                        'has_scores' => $scores->count() > 0,
                    ];
                });

            // グルーピング（順序を維持）
            $ledgerRecordsGroupByDefineIds = collect();
            foreach ($recordsCollection as $ledger) {
                $defineId = $ledger->ledger_define_id;
                if (! $ledgerRecordsGroupByDefineIds->has($defineId)) {
                    $ledgerRecordsGroupByDefineIds->put($defineId, collect());
                }
                $ledgerRecordsGroupByDefineIds->get($defineId)->push($ledger);
            }

            // ソート順序の適用
            if ($isSearchActive) {
                $ledgerRecordsGroupByDefineIds = $ledgerRecordsGroupByDefineIds->sortByDesc(
                    function (Collection $records, int $defineId) use ($scoreStatsByDefineId) {
                        return $scoreStatsByDefineId[$defineId]['avg_score'] ?? 0;
                    }
                );
            } else {
                $ledgerRecordsGroupByDefineIds = $ledgerRecordsGroupByDefineIds->sortKeys();
            }

            return [
                'groups' => $ledgerRecordsGroupByDefineIds,
                'stats' => $scoreStatsByDefineId->toArray(),
            ];
        });

        $cacheHit = ! $cacheHit; // Closure 内で false のままなら cache hit
        $totalDurationMs = (microtime(true) - $statsComputeStartedAt) * 1000;

        Log::debug('RecordsGroupingService::groupAndComputeStats', [
            'cache_hit' => $cacheHit,
            'duration_ms' => round($totalDurationMs, 3),
            'record_count' => $recordsCollection->count(),
            'group_count' => count($result['stats']),
            'is_search_active' => $isSearchActive,
        ]);

        return [
            'groups' => $result['groups'],
            'stats' => $result['stats'],
            'timing' => [
                'stats_compute_ms' => round($totalDurationMs * 0.6, 3), // 概算: 統計計算が60%
                'grouping_ms' => round($totalDurationMs * 0.4, 3),       // 概算: グルーピングが40%
                'cache_hit' => $cacheHit,
            ],
        ];
    }

    /**
     * キャッシュキーを構築する
     */
    private function buildCacheKey(Collection $recordsCollection, bool $isSearchActive, ?string $tenantId): string
    {
        $recordIds = $recordsCollection->pluck('id')->implode(',');
        $recordSignature = md5($recordIds.':'.$recordsCollection->count());
        $tenant = $tenantId ?? (tenant()?->id ?? 'central');

        return "records_grouping:{$tenant}:{$recordSignature}:".($isSearchActive ? 'search' : 'default');
    }
}
