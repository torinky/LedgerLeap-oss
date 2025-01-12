<?php

namespace App\Models;

use App\Enums\FolderPermissionType;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
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

    public function accessibleFolders(FolderPermissionType $permission)
    {
        return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
            ->withPivot('permission')
            ->wherePivot('permission', $permission->value)
            ->select('folders.*');
    }

    public function ManageableFolders()
    {
        return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
            ->withPivot('permission')
            ->wherePivot('permission', FolderPermissionType::ADMIN)
            ->select('folders.*');
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
}
