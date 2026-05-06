<?php

namespace App\Models;

use App\Casts\AsColumnDefinesArrayJson;
use App\Rules\UniqueAutoNumber;
use App\Rules\UniqueColumnValue;
use App\Traits\HasModelRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property Collection<int, \App\Models\ColumnDefine> $column_define
 *
 * @method static find(Route|object|string|null $route)
 * @method maxColumnId()
 */
class LedgerDefine extends Model
{
    use HasFactory, HasModelRoles, LogsActivity, SoftDeletes, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    private ?int $cachedMaxColumnId = null;

    private ?Collection $cachedColumnDefineKeyById = null;

    protected $casts = [
        'column_define' => AsColumnDefinesArrayJson::class,
        'workflow_enabled' => 'boolean',
        'confidentiality_scopes' => 'array',
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
        'confidentiality_level',
        'confidentiality_scopes',
    ];

    protected static function booted(): void
    {
        static::updating(function (LedgerDefine $ledgerDefine) {
            if ($ledgerDefine->isDirty()) {
                $ledgerDefine->version += 1;
            }
        });
    }

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

    /**
     * バリデーションルールを取得します。
     */
    public function getValidationRules(?int $ledgerId = null): array
    {
        $validationRules = [];

        foreach ($this->column_define as $column) {
            $columnId = $column->id;
            $columnName = 'content.'.$columnId;
            $inputType = $column->getInputType();

            // 1. InputTypeから型固有のルールを取得
            $rules = $inputType->getValidationRules();

            // 2. 共通のルールをマージ
            if ($column->type === 'chk') {
                $rules[] = \Illuminate\Validation\Rule::array();
                // 必須項目の場合、少なくとも1つ選択されていることを検証
                if ($column->required) {
                    $rules[] = new \App\Rules\RequiredCheckbox;
                }
            } else {
                // その他の型
                if ($column->required) {
                    array_unshift($rules, 'required');
                }
            }

            if ($column->unique) {
                if ($column->type === 'auto_number') {
                    $rules[] = new UniqueAutoNumber($this->id, $column, $ledgerId);
                } else {
                    // UniqueColumnValue 側に処理を集約（カスタム + 標準 unique のペアを返す）
                    $customUnique = new UniqueColumnValue($this->id, $columnId, $ledgerId);
                    $rules = array_merge($rules, $customUnique->toRules());
                }
            }

            // カラムごとのバリデーションルールを配列に追加
            $validationRules[$columnName] = $rules;
        }

        return $validationRules;
    }

    /**
     * バリデーション属性名を取得します。
     */
    public function getValidationAttributes(): array
    {
        $attributes = [];

        foreach ($this->column_define as $column) {
            $attributes["content.{$column->id}"] = $column->name;
        }

        return $attributes;
    }

    public function scopeSearchTags($query, $keywords)
    {
        if (empty($keywords)) {
            return $query;
        }

        foreach ((array) $keywords as $keyword) {
            $keyword = trim((string) $keyword);

            if ($keyword === '') {
                continue;
            }

            $query->whereHas('tags', function ($query) use ($keyword) {
                $query->where('name', 'LIKE', '%'.$keyword.'%');
            });
        }

        return $query;
    }

    /**
     * @return int
     */
    public function getMaxColumnIdAttribute()
    {
        if ($this->cachedMaxColumnId !== null) {
            return $this->cachedMaxColumnId;
        }

        return $this->cachedMaxColumnId = collect($this->column_define)->pluck('id')->max();
    }

    /**
     * @return Collection
     */
    private function getColumnDefineKeyByIdAttribute()
    {
        if ($this->cachedColumnDefineKeyById !== null) {
            return $this->cachedColumnDefineKeyById;
        }

        return $this->cachedColumnDefineKeyById = collect($this->column_define)->keyBy('id')->sortKeys();
    }

    /**
     * @return array
     */
    public function normalizeByColumnDefine($content)
    {
        $maxId = $this->getMaxColumnIdAttribute();
        $columnDefineKeyById = $this->getColumnDefineKeyByIdAttribute();

        $normalizedContent = is_array($content) ? $content : collect($content)->all();

        // 欠番を埋める
        for ($i = 0; $i <= $maxId; $i++) {
            if (! array_key_exists($i, $normalizedContent)) {
                if ($columnDefineKeyById->has($i)) {
                    $normalizedContent[$i] = in_array($columnDefineKeyById[$i]->type, ['chk', 'files'], true) ? [] : '';
                } else {
                    // 他の欠番（削除されたカラム等）も空文字で埋めてインデックスを維持する
                    $normalizedContent[$i] = '';
                }
            }
        }

        // キーで並び替え（ID順を維持）
        // values() は呼び出さず、連想配列（ID => Value）の状態を維持する。
        // これにより、後続の calculateAutoFillValues で ID ベースのアクセスが可能になる。
        // 最終的な添字配列化はモデルのキャスト(AsColumnArrayJson)に任せる。
        ksort($normalizedContent, SORT_NUMERIC);

        return $normalizedContent;
    }

    /**
     * 自動入力項目の値を計算する
     *
     * @param  array  $currentContent  既存の内容
     * @param  bool  $isUpdating  更新かどうか(falseなら新規作成)
     * @return array 更新された内容
     */
    public function calculateAutoFillValues(array $currentContent, bool $isUpdating = false): array
    {
        foreach ($this->column_define as $column) {
            $columnId = $column->id;
            $inputType = $column->getInputType();

            // 1. 自動入力ロジック (default_offset と overwrite_existing に統合)
            if ($inputType instanceof \App\Models\ColumnTypes\DateType) {
                $offset = $inputType->default_offset;
                $overwrite = $inputType->overwrite_existing;

                if (! empty($offset)) {
                    // オフセットがある場合は自動入力対象
                    if ($overwrite) {
                        // 「既存値を上書き」が ON の場合は常にセット（更新時含む）
                        $currentContent[$columnId] = $inputType->getDefaultDate($currentContent[$columnId] ?? null);
                    } elseif (! $isUpdating && empty($currentContent[$columnId])) {
                        // 「既存値を上書き」が OFF の場合は新規作成時かつ未設定の場合のみセット
                        $currentContent[$columnId] = $inputType->getDefaultDate();
                    }
                }
            }

            // 2. 正規化・クレンジング処理 (全カラム共通)
            // phone の全角変換や、number の半角変換などを保存直前に実行する
            // shouldConvertToJson が true の型（files, chk 等）は、キャスト側でシリアライズするため
            // ここでの convertColumnValue2Text（JSON文字列化）をスキップする
            // また、インデックス不整合を防ぐため、$currentContent 内に該当 $columnId が存在するか、
            // または defaultValue が必要な場合のみ処理を行う
            if (array_key_exists($columnId, $currentContent) && ! $inputType->shouldConvertToJson()) {
                $currentContent[$columnId] = $column->convertColumnValue2Text($currentContent[$columnId]);
            }
        }

        return $currentContent;
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
            ->setDescriptionForEvent(fn (string $eventName) => $this->getLogDescriptionForEvent($eventName));
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
