<?php

namespace App\Models;

//use App\Casts\AsCollection;

use App\Casts\AsColumnArrayJson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ledger extends Model
{
    use HasFactory;

    protected $casts = [
        'content' => AsColumnArrayJson::class,
    ];

    protected $fillable = [
        'content', 'ledger_define_id', 'creator_id', 'modifier_id'
    ];

    /**
     * @param Builder $query
     * @param $freeWord
     */
    public function scopeSearch(Builder $query, $freeWord)
    {
        $freeWord = trim($freeWord);
        if (empty($freeWord)) {
            return;
        }
//        dd($freeWord);
        $query->whereRaw("match(`content`) against (? IN BOOLEAN MODE)", [$freeWord]);
//        dd($query->toSql(), $query->getBindings());

    }


    /**
     * @param Builder $query
     * @param array $filter
     */
    public static function scopeContentsFilter(Builder $query, array $filter)
    {
        if (empty($filter)) {
            return;
        }

        foreach ($filter as $column => $filterStr) {
            $filterStr = trim($filterStr);
            if (empty($filterStr)) {
                continue;
            }

// シングルクォート内のバイドがカウントされていないっぽいのでプレースフォルダが使えない
// https://qiita.com/keizokeizo3/items/112f4785acd8bcf165d2
//            ->whereRaw("match(`content`) against ('*W? +?' IN BOOLEAN MODE)", [$column, $filter]);

// Mroongaの列番号は1始まり
            $mroongaColumnCount = $column + 1;
            $query->whereRaw("match(`content`) against ('*W" . (string)($mroongaColumnCount) . " +" . $filterStr . "' IN BOOLEAN MODE)");
        }

    }

    public function define()
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    public function ledgerDiff()
    {
        return $this->hasMany(LedgerDiff::class, 'ledger_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

}


