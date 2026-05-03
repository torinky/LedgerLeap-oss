<?php

namespace App\Services\Ledger;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class RecordsGroupingService
{
    /**
     * レコードコレクションを台帳定義ごとにグループ化し、統計情報を付与する
     *
     * @param  Collection|LengthAwarePaginator|Paginator  $ledgerRecords  ページネーション後のレコード
     * @param  bool  $isSearchActive  検索実行中かどうか（ソート順序の決定に使用）
     * @return array{
     *     groups: Collection<int, Collection>,
     *     stats: array<int, array{count:int,avg_score:float,max_score:float,min_score:float,has_scores:bool}>
     * }
     */
    public function groupAndComputeStats(Collection|LengthAwarePaginator|Paginator $ledgerRecords, bool $isSearchActive = false): array
    {
        $recordsCollection = $ledgerRecords instanceof LengthAwarePaginator || $ledgerRecords instanceof Paginator
            ? $ledgerRecords->getCollection()
            : $ledgerRecords;

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
    }
}
