<?php

namespace App\Services;

use App\Models\Ledger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class LedgerService
{
    /**
     * @return Builder[]|Collection
     */
    public function getLedgers()
    {
        return Ledger::orderBy('created_at', 'DESC')->get();
    }

    /**
     * @return Builder[]|Collection
     */
    public function searchLedgers(string $keyword)
    {
        //        return Ledger::freeword($keyword)->orderBy('created_at', 'DESC')->get();
        $result = Ledger::scopeSearch($keyword)->orderBy('created_at', 'DESC')->get();

        //        var_dump(DB::getQueryLog());
        return $result;

    }

    public function searchLedgersForApi(array $params)
    {
        $query = \App\Models\Ledger::query()->apiSearch($params);

        if (($params['mode'] ?? 'search') === 'count') {
            return ['total' => $query->count()];
        }

        $total = $query->count(); // ページネーション前に総件数を取得
        $limit = $params['limit'] ?? 10;
        $offset = $params['offset'] ?? 0;

        $ledgers = $query->offset($offset)->limit($limit)->get();

        return [
            'ledgers' => $ledgers,
            'total' => $total,
        ];
    }
}
