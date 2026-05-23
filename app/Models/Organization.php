<?php

namespace App\Models;

// use CubeAgency\FilamentTreeView\Traits\HasTreeView;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Studio15\FilamentTree\Concerns\InteractsWithTree;

class Organization extends Model
{
    //    use HasFactory, HasRoles, HasTreeView, LogsActivity, NodeTrait, SoftDeletes;
    use HasFactory, HasRoles, InteractsWithTree, LogsActivity, NodeTrait, SoftDeletes;

    protected $fillable = ['org_id', 'name', 'abbreviation', 'description', 'parent_id'];

    public $guard_name = 'web';

    protected static function booted()
    {
        static::saved(function ($organization) {
            Cache::forget("confidentiality:{$organization->tenant_id}:scopes");
        });

        static::deleted(function ($organization) {
            Cache::forget("confidentiality:{$organization->tenant_id}:scopes");
        });
    }

    public static function getTreeLabelAttribute(): string
    {
        return 'name';  // 例：タイトル列が `name` の場合
    }

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('organization')
            ->setDescriptionForEvent(fn (string $eventName) => $this->getLogDescriptionForEvent($eventName));
    }

    /**
     * ログに記録する際の追加情報
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        // 言語ファイルからdescriptionを取得
        $key = "activitylog.organization_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.organization_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "組織が{$eventName}されました";
    }

    /**
     * @return BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_organizations')->withPivot('is_primary');
    }

    /**
     * 親組織から継承された権限を含む全ての権限を取得
     *
     * @return mixed
     */
    public function getAllPermissions()
    {
        $allPermissions = $this->permissions;

        foreach ($this->ancestors as $ancestor) {
            $allPermissions = $allPermissions->merge($ancestor->permissions);
        }

        return $allPermissions->unique('id');
    }

    /**
     * 親組織から継承された役割を含む全ての役割を取得
     *
     * @return mixed
     */
    public function getAllRoles()
    {
        $allRoles = $this->roles;

        foreach ($this->ancestors as $ancestor) {
            $allRoles = $allRoles->merge($ancestor->roles);
        }

        return $allRoles->unique('id');
    }

    public function hasPermissionWithInheritance($permission)
    {
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        foreach ($this->ancestors as $ancestor) {
            if ($ancestor->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasRoleWithInheritance($role)
    {
        if ($this->hasRole($role)) {
            return true;
        }

        foreach ($this->ancestors as $ancestor) {
            if ($ancestor->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function getDirectRoles()
    {
        return $this->roles;
    }

    public function getInheritedRoles()
    {
        $inheritedRoles = collect();
        foreach ($this->ancestors as $ancestor) {
            $inheritedRoles = $inheritedRoles->merge($ancestor->roles);
        }

        return $inheritedRoles->unique('id')->diff($this->getDirectRoles());
    }

    public function getDirectPermissions()
    {
        return $this->permissions;
    }

    public function getInheritedPermissions()
    {
        $inheritedPermissions = collect();
        foreach ($this->ancestors as $ancestor) {
            $inheritedPermissions = $inheritedPermissions->merge($ancestor->getAllPermissions());
        }

        return $inheritedPermissions->unique('id')->diff($this->getDirectPermissions());
    }

    public function getAllUniquePermissions()
    {
        return $this->getAllPermissions()->unique('id');
    }

    public function getDirectPermissionsViaRoles()
    {
        return $this->getDirectRoles()->flatMap->permissions->unique('id');
    }

    public function getInheritedPermissionsViaRoles()
    {
        return $this->getInheritedRoles()->flatMap->permissions->unique('id');
    }

    public function getAllUniquePermissionsViaRoles()
    {
        return $this->getAllRoles()->flatMap->permissions->unique('id');
    }

    /**
     * 上位組織を含めたフルネームを返すアクセサ。
     *
     * 'ancestors' リレーションが Eager Loading されている場合はそれを効率的に利用し、
     * N+1問題を回避してフルパスを生成します。
     */
    public function getFullNameAttribute(): string
    {
        // 'ancestors' リレーションが Eager Loading されているかチェック
        if ($this->relationLoaded('ancestors')) {
            // 読み込み済みの祖先の名前コレクションの末尾に、自身の名前を追加する
            $path = $this->ancestors->pluck('name')->push($this->name);

            // ' > ' 区切りで文字列に変換して返す (より階層が分かりやすくなります)
            return $path->join(' > ');
        }

        // フォールバック: Eager Loading されていない場合 (個別のモデル表示などで呼ばれる)
        // 従来のDBクエリを実行する
        return $this->ancestorsAndSelf($this->id)->pluck('name')->join(' > ');
    }

    /**
     * 親組織から継承された役割を含む全てのユニークな役割を取得
     * (user-combined-roles-permissions.blade.phpとの互換性のため)
     *  For Fillament
     *
     * @return mixed
     */
    public function getAllUniqueRoles()
    {
        return $this->getAllRoles();
    }
}
