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
     * @param string $keyword
     * @return Builder[]|Collection
     */
    public function searchLedgers(string $keyword)
    {
//        return Ledger::freeword($keyword)->orderBy('created_at', 'DESC')->get();
        $result = Ledger::scopeSearch($keyword)->orderBy('created_at', 'DESC')->get();
//        var_dump(DB::getQueryLog());
        return $result;

    }


}
