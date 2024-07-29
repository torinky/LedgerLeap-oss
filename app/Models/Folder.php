<?php

namespace App\Models;

use App\Traits\HasModelRoles;
use CubeAgency\FilamentTreeView\Traits\HasTreeView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;

class Folder extends Model
{
    use HasFactory, HasModelRoles, HasTreeView, NodeTrait, SoftDeletes;

    protected $fillable = [
        'title', 'modifier_id', 'creator_id',
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
        return $this->descendantsAndSelf($this->id)
            ->reduce(fn($carry, $folder) => $carry + $folder->ledgerDefines()->count(), 0);
    }

    /**
     * 子孫フォルダーのすべての件数を取得します。
     *
     * @return int
     */
    public function descendantCount()
    {
        return $this->descendants()->count();
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

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
    }
}
