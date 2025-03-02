<?php

namespace App\Models;

use App\Casts\AsColumnDefinesArrayJson;
use App\Traits\HasModelRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Route;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @method static find(Route|object|string|null $route)
 * @method maxColumnId()
 */
class LedgerDefine extends Model
{
    use HasFactory, HasModelRoles, LogsActivity, SoftDeletes;

    protected $casts = [
        'column_define' => AsColumnDefinesArrayJson::class,
    ];

    protected $fillable = [
        'title', 'column_define',
        'folder_id',
        'creator_id',
        'modifier_id',
        'create_description',
        'list_description',
        'detail_description',
    ];

    public function ledgers()
    {
        return $this->hasMany(Ledger::class, 'ledger_define_id');
    }

    public function tags()
    {
        return $this->hasMany(Tag::class, 'ledger_define_id');
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    public function scopeSearchTags($query, $keywords)
    {
        if (empty($keywords)) {
            return $query;
        }

        return $query->whereHas('tag', function ($query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $query->where('name', 'LIKE', '%' . $keyword . '%');
            }
        });
    }

    /**
     * @return int
     */
    public function getMaxColumnIdAttribute()
    {
        return collect($this->column_define)->pluck('id')->max();
    }

    /**
     * @return Collection
     */
    private function getColumnDefineKeyByIdAttribute()
    {
        return collect($this->column_define)->keyBy('id')->sortKeys();

    }

    /**
     * @return array
     */
    public function normalizeByColumnDefine($content)
    {
        $maxId = $this->getMaxColumnIdAttribute();
        $columnDefineKeyById = $this->getColumnDefineKeyByIdAttribute();

        // contentをcollectionに変換
        $contentCollection = collect($content);

        // 欠番を埋める
        for ($i = 0; $i <= $maxId; $i++) {
            if (!$contentCollection->has($i)) {
                if ($columnDefineKeyById->has($i)) {
                    $contentCollection[$i] = $columnDefineKeyById[$i]->type === 'chk' ? [] : '';
                }
            }
        }

        // キーで並び替え
        $sortedContentArray = $contentCollection->sortKeys();

        // 数字添字配列に作り直し
        return $sortedContentArray->values()->toArray();
    }

    public function hasPermissionTo($permission): bool
    {
        return $this->roles->flatMap->permissions->contains('name', $permission);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
//            ->logOnly(['title', 'folder_id', 'column_define','create_description','list_description','detail_description','deleted_at']) // 変更を監視する属性
            ->logOnlyDirty() // 変更があった場合のみ記録
            ->dontSubmitEmptyLogs() // 空のログは記録しない
            ->logFillable()
            ->setDescriptionForEvent(fn(string $eventName) => "LedgerDefine has been {$eventName}");
        // ->logUnguarded() // ガードされていないすべての属性をログに記録 (fillable の逆)
        // ->dontLogIfAttributesChangedOnly(['column_define']) // 特定の属性のみが変更された場合はログを記録しない
    }
}
