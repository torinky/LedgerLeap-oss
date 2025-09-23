<?php

namespace App\Models;

use App\Casts\AsColumnDefinesArrayJson;
use App\Traits\HasModelRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @method static find(Route|object|string|null $route)
 * @method maxColumnId()
 */
class LedgerDefine extends Model
{
    use HasFactory, HasModelRoles, LogsActivity, SoftDeletes, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    protected $casts = [
        'column_define' => AsColumnDefinesArrayJson::class,
        'workflow_enabled' => 'boolean',
    ];

    protected $fillable = [
        'title', 'column_define',
        'folder_id',
        'creator_id',
        'modifier_id',
        'create_description',
        'list_description',
        'detail_description',

        'version',
        'recommended_inspector_id',
        'recommended_approver_id',
        'recommended_inspector_role_id',
        'recommended_approver_role_id',
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

    public function hasPermissionTo($permission, ?string $guardName): bool
    {
        return $this->roles->flatMap->permissions->contains('name', $permission);
    }

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('ledger_define')
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => $this->getLogDescriptionForEvent($eventName));
    }

    /**
     * ログに記録する際の追加情報
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        // 言語ファイルからdescriptionを取得
        $key = "activitylog.ledger_define_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.ledger_define_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "台帳定義が{$eventName}されました";
    }

    public function recommendedInspector()
    {
        return $this->belongsTo(User::class, 'recommended_inspector_id');
    }

    public function recommendedApprover()
    {
        return $this->belongsTo(User::class, 'recommended_approver_id');
    }

    public function recommendedInspectorRole()
    {
        // Role モデルが Spatie\Permission\Models\Role の場合
        return $this->belongsTo(config('permission.models.role'), 'recommended_inspector_role_id');
        // 自作 Role モデルの場合は App\Models\Role::class を指定
        // return $this->belongsTo(Role::class, 'recommended_inspector_role_id');
    }

    public function recommendedApproverRole()
    {
        return $this->belongsTo(config('permission.models.role'), 'recommended_approver_role_id');
        // return $this->belongsTo(Role::class, 'recommended_approver_role_id');
    }
}
