<?php

namespace App\Models;

//use App\Casts\AsCollection;

use App\Casts\AsColumnArrayJson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * モデルの作成と更新イベントを処理するメソッドです。
     *
     * @return void
     */
    protected static function booted()
    {
        static::creating(function ($ledger) {
            // create イベントの処理
            $ledger->normalizeContent();
        });

        static::updating(function ($ledger) {
            // update イベントの処理
            $ledger->normalizeContent();
        });
    }

    /**
     * 指定されたフリーワードで content を検索するスコープです。
     *
     * @param Builder $query
     * @param string $freeWord
     * @return void
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
     * 指定されたフィルタ条件で content をフィルタリングするスコープです。
     *
     * @param Builder $query
     * @param array $filter
     * @return void
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

    /**
     * LedgerDefine モデルへのリレーションを定義します。
     *
     * @return BelongsTo
     */
    public function define()
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    /**
     * LedgerDiff モデルへのリレーションを定義します。
     *
     * @return HasMany
     */
    public function ledgerDiff()
    {
        return $this->hasMany(LedgerDiff::class, 'ledger_id');
    }

    /**
     * User モデルへの creator リレーションを定義します。
     *
     * @return BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * User モデルへの modifier リレーションを定義します。
     *
     * @return BelongsTo
     */
    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }


    /**
     * contentをLedgerDefine情報に基づいて正規化
     *
     * LedgerDefineの情報を元に、contentを正規化します。
     * 歯抜けのIDを埋め、キー番号がcolumnDefineIDと一致するようにします。
     * また、キーで並び替えて、数字添字配列に作り直します。
     *
     * @return void
     */
    protected function normalizeContent()
    {
        // 歯抜けのIDを埋め、キー番号がcolumnDefineIDと一致するようにする
        $maxId = collect($this->define->column_define)->pluck('id')->max();
        $columnDefineIds = collect($this->define->column_define)->pluck('id')->toArray();

        // contentを配列に変換
        $contentArray = (array)$this->content;

        for ($i = 0; $i <= $maxId; $i++) {
            if (!in_array($i, $columnDefineIds, true)) {
                $contentArray[$i] = '';
            }
        }

        // キーで並び替え
        ksort($contentArray);

        // 数字添字配列に作り直し
        $this->content = array_values($contentArray);
    }

}


