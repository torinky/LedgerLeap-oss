<?php

namespace App\Models;

//use App\Casts\AsCollection;

use App\Casts\AsColumnArrayJson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array $content_attached
 * @property array $content
 * @property BelongsTo $define
 * @method static create(array $array)
 * @method static find(string $ledgerId)
 */
class Ledger extends Model
{
    use HasFactory;

    protected $casts = [
        'content' => AsColumnArrayJson::class,
        'content_attached' => AsColumnArrayJson::class,
    ];

    protected $fillable = [
        'content', 'content_attached', 'ledger_define_id', 'creator_id', 'modifier_id'
    ];

    /**
     * モデルの作成と更新イベントを処理するメソッドです。
     *
     * @return void
     */
    /*    protected static function booted(): void
        {
            static::saving(static function ($ledger) {
                // update イベントの処理
                $ledger->normalizeContent();
            });

        }*/

    /**
     * 指定されたフリーワードで content を検索するスコープです。
     *
     * @param Builder $query
     * @param string $freeWord
     * @return void
     */
    public function scopeSearch(Builder $query, string $freeWord)
    {
        $freeWord = trim($freeWord);
        if (empty($freeWord)) {
            return;
        }
//        dd($freeWord);
//        $query->whereRaw("match(`content`) against (? IN BOOLEAN MODE)", [$freeWord]);
//        $query->whereRaw("match(`content`,`content_attached`) against (? IN BOOLEAN MODE)", [$freeWord]);
        $query->where(function (Builder $q) use ($freeWord) {
            $q->whereRaw("match(`content`) against (? IN BOOLEAN MODE)", [$freeWord])
                ->orWhereRaw("match(`content_attached`) against (? IN BOOLEAN MODE)", [$freeWord]);
        });

//        dd($query->toSql(), $query->getBindings());

    }


    /**
     * 指定されたフィルタ条件で content をフィルタリングするスコープです。
     *
     * @param Builder $query
     * @param array $filter
     * @return void
     */
    public static function scopeContentsFilter(Builder $query, array $filter): void
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
//            $query->whereRaw("match(`content`) against ('*W" . $mroongaColumnCount . " +" . $filterStr . "' IN BOOLEAN MODE)");
            $query->whereRaw("match(`content`,`content_attached`) against ('*W" . $mroongaColumnCount . " +" . $filterStr . "' IN BOOLEAN MODE)");
            /*            $query->where(function (Builder $q)use($mroongaColumnCount,$filterStr) {
                            $q->whereRaw("match(`content`) against ('*W" . $mroongaColumnCount . " +" . $filterStr . "' IN BOOLEAN MODE)")
                                ->orWhereRaw("match(`content_attached`) against ('*W" . $mroongaColumnCount . " +" . $filterStr . "' IN BOOLEAN MODE)");
                        });*/
        }

    }

    /**
     * LedgerDefine モデルへのリレーションを定義します。
     *
     * @return BelongsTo
     */
    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    /**
     * LedgerDiff モデルへのリレーションを定義します。
     *
     * @return HasMany
     */
    public function ledgerDiff(): HasMany
    {
        return $this->hasMany(LedgerDiff::class, 'ledger_id');
    }

    /**
     * User モデルへの creator リレーションを定義します。
     *
     * @return BelongsTo
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * User モデルへの modifier リレーションを定義します。
     *
     * @return BelongsTo
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }


}


