<?php

namespace App\Models;

use App\Enums\FolderPermissionType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'notification_type_id',
    ];

    protected $casts = [
        'permission' => FolderPermissionType::class,
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

    /**
     * Get the notification type that the permission relates to.
     */
    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }
}
