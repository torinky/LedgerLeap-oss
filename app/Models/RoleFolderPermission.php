<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Cache;

class RoleFolderPermission extends Pivot
{
    protected $table = 'role_folder_permissions';

    protected $fillable = [
        'role_id',
        'folder_id',
        'permission',
        'modifier_id',
    ];

    protected static function booted()
    {
        static::created(function ($roleFolderPermission) {
            Cache::forget('role_writable_folders_' . $roleFolderPermission->role_id);
            Cache::forget('folder_permissions_' . $roleFolderPermission->folder_id . '_' . $roleFolderPermission->role_id);
        });

        static::updated(function ($roleFolderPermission) {
            //            dd('updated : ',$roleFolderPermission);
            Cache::forget('role_writable_folders_' . $roleFolderPermission->role_id);
            Cache::forget('folder_permissions_' . $roleFolderPermission->folder_id . '_' . $roleFolderPermission->role_id);
        });

        static::deleted(function ($roleFolderPermission) {
            Cache::forget('role_writable_folders_' . $roleFolderPermission->role_id);
            Cache::forget('folder_permissions_' . $roleFolderPermission->folder_id . '_' . $roleFolderPermission->role_id);
        });
    }
}
