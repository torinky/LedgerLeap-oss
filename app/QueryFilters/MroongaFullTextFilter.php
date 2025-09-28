<?php

namespace App\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\DB; // この行を追加

class MroongaFullTextFilter implements Filter
{
    public function __construct(protected array $columns)
    {
    }

    public function __invoke(Builder $query, $value, string $property)
    {

        // ユーザー入力をスペースで分割し、各単語の前に+を付けてAND検索文字列を生成
        $keywords = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $searchString = '';
        if ($keywords) {
            $searchString = '+' . implode(' +', $keywords);
        }

        if (empty($searchString)) {
            return;
        }

        // カンマ区切りでカラムを結合
        $columns = implode(', ', array_map(fn($col) => "`{$col}`", $this->columns));
        $query->whereRaw("match({$columns}) against (? IN BOOLEAN MODE)", [$searchString]);
    }
}
