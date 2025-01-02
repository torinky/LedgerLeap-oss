<?php

namespace App\Models;

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
        static::created(function ($role) {
            Cache::forget('role_writable_folders_' . $role->id);
        });

        static::updated(function ($role) {
            Cache::forget('role_writable_folders_' . $role->id);
            foreach (Folder::all() as $folder) {
                Cache::forget('folder_permissions_' . $folder->id . '_' . $role->id);
            }
        });

        static::deleted(function ($role) {
            Cache::forget('role_writable_folders_' . $role->id);
            foreach (Folder::all() as $folder) {
                Cache::forget('folder_permissions_' . $folder->id . '_' . $role->id);
            }
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
        return $this->belongsToMany(Folder::class, 'role_folder_permissions', 'role_id', 'folder_id')
            ->withPivot('permission')
            ->wherePivot('permission', 'read')
            ->select('folders.*');
    }

    public function writableFolders()
    {
        $cacheKey = 'role_writable_folders_' . $this->id;

        return Cache::remember($cacheKey, now()->addMinutes(60), function () {
            return $this->belongsToMany(Folder::class, 'role_folder_permissions', 'role_id', 'folder_id')
                ->withPivot('permission')
                ->wherePivot('permission', 'write')
                ->select('folders.*');
        });
    }

    public function folderPermissions()
    {
        return $this->belongsToMany(Folder::class, 'role_folder_permissions', 'role_id', 'folder_id')
            ->withPivot('permission')
            ->withTimestamps();
    }
}
