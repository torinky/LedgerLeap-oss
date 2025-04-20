<?php

namespace App\Models;

// use App\Casts\AsCollection;

use App\Casts\AsColumnArrayJson;
use App\Enums\WorkflowStatus;
use App\Services\Ledger\SearchContext;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property array $content_attached
 * @property array $content
 * @property BelongsTo $define
 *
 * @method static create(array $array)
 * @method static find(string $ledgerId)
 */
class Ledger extends Model
{
    use HasFactory, LogsActivity;

    protected $casts = [
        'content' => AsColumnArrayJson::class,
        'content_attached' => AsColumnArrayJson::class,
        'status' => WorkflowStatus::class,
    ];

    protected $fillable = [
        'content', 'content_attached', 'ledger_define_id', 'creator_id', 'modifier_id', 'status', 'latest_diff_id', 'version'
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
            $q->whereRaw('match(`content`) against (? IN BOOLEAN MODE)', [$freeWord])
                ->orWhereRaw('match(`content_attached`) against (? IN BOOLEAN MODE)', [$freeWord]);
        });

        //        dd($query->toSql(), $query->getBindings());

    }

    /**
     * 指定されたSearchContextで content を検索するスコープです。
     *
     * @return void
     */
    public function scopeSearchContext(Builder $query, SearchContext $searchContext)
    {

        foreach ($searchContext->keywords as $keyword) {
            $queryText = $searchContext->getFlattenedSynonymsForKeyword($keyword);
            $this->scopeSearch($query, implode(' ', $queryText));
        }

    }

    /**
     * 指定されたフィルタ条件で content をフィルタリングするスコープです。
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
            $query->where(function (Builder $q) use ($mroongaColumnCount, $filterStr) {
                $q->whereRaw("match(`content`) against ('*W" . $mroongaColumnCount . ' +"' . $filterStr . "\"' IN BOOLEAN MODE)")
                    ->orWhereRaw("match(`content_attached`) against ('*W" . $mroongaColumnCount . ' +' . $filterStr . "' IN BOOLEAN MODE)");
            });
        }

    }

    /**
     * LedgerDefine モデルへのリレーションを定義します。
     */
    public function define(): BelongsTo
    {
        return $this->belongsTo(LedgerDefine::class, 'ledger_define_id');
    }

    /**
     * LedgerDiff モデルへのリレーションを定義します。
     */
    public function ledgerDiff(): HasMany
    {
        return $this->hasMany(LedgerDiff::class, 'ledger_id');
    }

    /**
     * User モデルへの creator リレーションを定義します。
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * User モデルへの modifier リレーションを定義します。
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    /**
     * Ledgerに関連するAttachedFileを取得する。
     *
     * @return HasMany
     */
    public function attachedFiles()
    {
        return $this->hasMany(AttachedFile::class);
    }

    /**
     * Ledgerを削除する際に関連するAttachedFileも削除する。
     *
     * @return bool|null
     *
     * @throws Exception
     */
    public function delete()
    {
        // まず関連するAttachedFileを削除する
        $this->attachedFiles()->delete();

        // 差分テーブルのレコードも削除
        $this->ledgerDiff()->delete();

        // 親のdeleteメソッドを呼び出す
        return parent::delete();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'content', 'ledger_define_id']) // 変更を監視する属性
            ->logOnlyDirty() // 変更があった場合のみ記録
            ->dontSubmitEmptyLogs() // 空のログは記録しない
            ->setDescriptionForEvent(fn(string $eventName) => $this->getLogDescriptionForEvent($eventName))
            ->logFillable();
        // ->logUnguarded() // ガードされていないすべての属性をログに記録 (fillable の逆)
        // ->dontLogIfAttributesChangedOnly(['column_define']) // 特定の属性のみが変更された場合はログを記録しない
    }

    /**
     * ログに記録する際の追加情報
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        // 言語ファイルからdescriptionを取得
        $key = "activitylog.ledger_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.ledger_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "台帳が{$eventName}されました";
    }

    public function folder()
    {
        return $this->define->folder();
    }

    /**
     * 最新の LedgerDiff レコードへのリレーション (修正)
     */
    public function latestDiff(): BelongsTo // <<<--- BelongsTo に変更
    {
        return $this->belongsTo(LedgerDiff::class, 'latest_diff_id');
    }
    
    /**
     * 現在のバージョンに対応する LedgerDiff レコードへのリレーション (オプション)
     * $ledger->diffForVersion(3) のように使う場合。より複雑。
     * 通常は LedgerDiff 側から ledger_id で検索する方が多い。
     */
    // public function diffForVersion(int $version) { ... }

    /**
     * 編集がロックされているか (APPROVED 状態か) を判定するヘルパー
     */
    public function isLocked(): bool
    {
        return $this->status === WorkflowStatus::APPROVED;
    }
}
