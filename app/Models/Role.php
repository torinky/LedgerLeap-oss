<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Cache;
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
        return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
            ->withPivot('permission')
            ->wherePivot('permission', 'read')
            ->select('folders.*');
    }

    /**
     * ロールに関連する書き込み可能なフォルダーを取得します。
     *
     * このメソッドは、現在のロールが書き込み権限を持つフォルダーを取得します。
     * 結果はパフォーマンス向上のために60分間キャッシュされます。
     *
     * @return Collection 書き込み権限を持つフォルダーのコレクション。
     */
    public function writableFolders()
    {
        $cacheKey = 'role_writable_folders_' . $this->id;

        return Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return $this->belongsToMany(Folder::class, RoleFolderPermission::class, 'role_id', 'folder_id')
                ->withPivot('permission')
                ->wherePivot('permission', 'write')
                ->select('folders.*');
        });
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
