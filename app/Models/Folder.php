<?php

namespace App\Models;

use App\Enums\FolderPermissionType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\PermissionRegistrar;
use Studio15\FilamentTree\Concerns\InteractsWithTree;

//use CubeAgency\FilamentTreeView\Traits\HasTreeView;

class Folder extends Model
{
    use HasFactory, LogsActivity, NodeTrait, SoftDeletes, InteractsWithTree;

    protected $fillable = [
        'title', 'modifier_id', 'creator_id', 'parent_id',
    ];

    protected $guard_name = ['web', 'api'];

    /**
     * ツリー表示で使用するラベルのカラム名を返します。
     *
     * @return string
     */
    public static function getTreeLabelAttribute(): string
    {
        return 'title';
    }

    protected static function booted()
    {
        static::created(function ($folder) {
            Cache::forget('folder_tree_list_');
        });

        static::updated(function ($folder) {
            Cache::forget('folder_tree_list_');
            foreach (Role::all() as $role) {
                Cache::forget('folder_permissions_' . $folder->id . '_' . $role->id);
                Cache::forget('role_writable_folders_' . $role->id);
            }
        });

        static::deleted(function ($folder) {
            Cache::forget('folder_tree_list_');
            foreach (Role::all() as $role) {
                Cache::forget('folder_permissions_' . $folder->id . '_' . $role->id);
                Cache::forget('role_writable_folders_' . $role->id);
            }
        });
    }

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

    /**
     * 与えられたノードからツリー形式のオプション配列を生成します。
     * 各ノードのタイトルは、階層を表すプレフィックスが付いたものがオプションのキーとなります。
     *
     * @param Collection $nodes ルートノードのコレクション
     * @return array 階層化されたオプション配列
     */
    public static function treeList($nodes)
    {
        $cacheKey = 'folder_tree_list_';

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($nodes) {
            $options = [];
            $traverse = function ($categories, $prefix = '-') use (&$traverse, &$options) {
                foreach ($categories as $category) {
                    $options[$category->id] = $prefix . ' ' . $category->title;

                    $traverse($category->children, $prefix . '-');
                }
            };

            $traverse($nodes);

            return $options;
        });
    }

    /**
     * 自身と祖先のすべての組織に役割を継承しているかどうかを確認します。
     *
     * @return Collection 継承された役割のコレクション
     */
    public function getAllRoles()
    {
        $allRoles = $this->roles;

        foreach ($this->ancestors as $ancestor) {
            $allRoles = $allRoles->merge($ancestor->roles);
        }

        return $allRoles->unique('id');
    }

    public function roles()
    {
        return $this->morphedByMany(Role::class, 'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }

    /**
     *  指定されたロールがこのフォルダに対して特定の権限を持っているか、親フォルダから権限を継承しているかをチェックします。
     *
     * @param Role $role チェックするロール
     * @param string $permission チェックする権限
     */
    public function hasPermissionWithInheritance(Role $role, string $permission): bool
    {
        $allPermissions = $this->getAllPermissionsWithInheritance($role);

        return in_array($permission, $allPermissions);
    }

    /**
     *  指定されたロールが直接このフォルダに対して特定の権限を持っているかをチェックします。
     *
     * @param Role $role チェックするロール
     * @param string $permission チェックする権限
     */
    public function hasDirectPermission(Role $role, string $permission): bool
    {
        return $role->folderPermissions()
            ->where('folder_id', $this->id)
            ->wherePivot('permission', $permission)
            ->exists();

    }

    /**
     * 指定されたロールが直接このフォルダに対して持っているすべての権限を返します。
     *
     * @param Role $role チェックするロール
     */
    public function getDirectPermissions(Role $role): array
    {
        return $role->folderPermissions()
            ->where('folder_id', $this->id)
            ->pluck('permission')
            ->toArray();
    }

    /**
     * 指定されたロールが持つ、このフォルダとその祖先フォルダのすべての権限を返します。
     *
     * @param Role $role チェックするロール
     */
    public function getAllPermissionsWithInheritance(Role $role): array
    {
        $cacheKey = 'folder_permissions_' . $this->id . '_' . $role->id; // キャッシュキーを生成

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($role) {
            $permissions = $this->getDirectPermissions($role);
            if ($this->parent) {
                $permissions = array_merge($permissions, $this->parent->getAllPermissionsWithInheritance($role));
            }

            return array_unique($permissions);
        });
    }

    /**
     * 指定された権限を持つロールに紐づくフォルダを取得する
     *
     * @param FolderPermissionType $permission 'write', 'read', 'manageable' など
     * @return BelongsToMany
     */
    public function accessibleRoles(?FolderPermissionType $permission = null)
    {
        if (empty($permission)) {
            return $this->belongsToMany(Role::class, RoleFolderPermission::class, 'folder_id', 'role_id')
                ->withPivot('permission')
                ->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF]);
        }

        return $this->belongsToMany(Role::class, RoleFolderPermission::class, 'folder_id', 'role_id')
            ->withPivot('permission')
            ->wherePivot('permission', $permission->value)
            ->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF]);
    }


    public function notificationSettings()
    {
        return $this->belongsToMany(Role::class, 'role_folder_permissions', 'folder_id', 'role_id')
            ->withPivot('notification_type_id', 'permission')
            ->withTimestamps();
        //            ->using(RoleFolderPermission::class)
        //            ->as('setting')
    }

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('folder')
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
        $key = "activitylog.folder_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.folder_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "フォルダーが{$eventName}されました";
    }

    public function folder()
    {
        return $this;
    }

    /**
     * このフォルダに設定された必須点検ロールを取得するリレーション
     */
    public function requiredInspectorRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'folder_required_roles', 'folder_id', 'role_id')
            ->wherePivot('type', 'inspector')
            ->withTimestamps();
    }

    /**
     * このフォルダに設定された必須承認ロールを取得するリレーション
     */
    public function requiredApproverRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'folder_required_roles', 'folder_id', 'role_id')
            ->wherePivot('type', 'approver')
            ->withTimestamps();
    }
}
