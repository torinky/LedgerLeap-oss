<?php

namespace App\Models;

use Fureev\Trees\{NestedSetTrait};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

//class Folder extends Model implements TreeConfigurable
class Folder extends Model
{
    use NestedSetTrait;

    protected $fillable = [
        'title', 'modifier_id', 'creator_id'
    ];

    public function ledgerDefines()
    {
        return $this->hasMany(LedgerDefine::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function tag()
    {
        return $this->hasMany(Tag::class, 'ledger_define_id');
    }

    /**
     * 子孫フォルダーのすべての`LedgerDefine`モデルの件数を取得します。
     *
     * @return int
     */
    public function descendantLedgerDefinesCount()
    {
        return $this->descendants(null, true)->get()
            ->reduce(fn($carry, $folder) => $carry + $folder->ledgerDefines()->count(), 0);
    }

    /**
     * 子孫フォルダーのすべての件数を取得します。
     *
     * @return int
     */
    public function descendantCount()
    {
        return $this->descendants(null, false)->count();
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
