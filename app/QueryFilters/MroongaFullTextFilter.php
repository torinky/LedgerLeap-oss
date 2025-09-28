<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB; // この行を追加

class MroongaFullTextFilter implements Filter
{
    public function __construct(array $columns)
    {
        Log::info('MroongaFullTextFilter: __construct called', ['columns' => $columns]); // この行を追加
        $this->columns = $columns;
    }
    public function __invoke(Builder $query, $value, string $property)
    {
        Log::info('MroongaFullTextFilter: __invoke called', ['columns' => $this->columns, 'value' => $value]);

        $query->where(function (Builder $q) use ($value) {
            $escapedValue = addslashes($value); // 値をエスケープ
            $q->whereRaw("match(`content`) against (mroonga_escape('{$escapedValue}') IN BOOLEAN MODE)");
            $q->orWhereRaw("match(`content_attached`) against (mroonga_escape('{$escapedValue}') IN BOOLEAN MODE)");
        });
    }
}
