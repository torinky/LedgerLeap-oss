<?php

namespace App\Models;

use App\Enums\FolderPermissionType;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    use LogsActivity, Notifiable;

    protected $fillable = [
        'name', 'guard_name',
        'description',
    ];

    protected static function booted()
    {
        static::updated(function ($role) {
            // ユーザーに関連するキャッシュをクリア
            $role->users()->each(function ($user) {
                app(WritableFolderRepository::class)->clearAllCache($user);
            });
        });

        static::deleted(function ($role) {
            // ユーザーに関連するキャッシュをクリア
            $role->users()->each(function ($user) {
                app(WritableFolderRepository::class)->clearAllCache($user);
            });
        });
    }

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('role')
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
        $key = "activitylog.role_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.role_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "ロールが{$eventName}されました";
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'role_tag')
            ->using(RoleTag::class);
    }

    public function organizations()
    {
        return $this->morphedByMany(Organization::class, 'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }

    public function folders()
    {
        return $this->morphedByMany(Folder::class, 'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }

    public function readableFolders()
    {
        return $this->accessibleFolders(FolderPermissionType::READ);
    }

    public function writableFolders()
    {
        return $this->accessibleFolders(FolderPermissionType::WRITE);
    }

    public function manageableFolders()
    {
        return $this->accessibleFolders(FolderPermissionType::ADMIN);
    }

    public function accessibleFolders($permission = null)
    {
        if (empty($permission)) {
            return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
                ->withPivot('permission')
                ->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
                ->select('folders.*')
                ->withTimestamps();
        }

        return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
            ->withPivot('permission')
            ->wherePivot('permission', $permission->value)
            ->whereNotIn('permission', [FolderPermissionType::NOTIFY_ON, FolderPermissionType::NOTIFY_OFF])
            ->select('folders.*')
            ->withTimestamps();
    }

    /**
     * ロールに関連するフォルダー権限を取得する
     *
     * このメソッドは、現在のロールに関連付けられたフォルダーとその権限のコレクションを取得します。
     * RoleFolderPermissionピボットテーブルを介して多対多のリレーションシップを使用しています。
     *
     * @return BelongsToMany リレーションシップのクエリビルダーインスタンス
     */
    public function folderPermissions()
    {
        return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
            ->withPivot('permission')
            ->withTimestamps();
    }

    /**
     * The folders that belong to the role and have notification settings.
     */
    public function notificationSettings(): BelongsToMany
    {
        return $this->belongsToMany(
            Folder::class,
            'role_folder_permissions', // 中間テーブル名
            'role_id', // 現在のモデル (Role) の外部キー
            'folder_id' // 関連モデル (Folder) の外部キー
        )
            ->withPivot('notification_type_id') // 中間テーブルのカラム
            ->withTimestamps(); // 中間テーブルのタイムスタンプを更新
    }

    public function roleFolderPermissions(): HasMany
    {
        return $this->hasMany(RoleFolderPermission::class);
    }

    /**
     * Belongs to relationship for notification type.
     *  RoleFolderPermission と NotificationType を経由して紐付いているが、ここで宣言しなければならない理由は不明
     */
    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }
}
