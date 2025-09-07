<?php

namespace App\Models;

use App\Enums\FolderPermissionType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Support\Facades\Log;

class RoleFolderPermission extends Model
{
    use LogsActivity, \Stancl\Tenancy\Database\Concerns\BelongsToTenant;

    protected $table = 'role_folder_permissions';

    protected $fillable = [
        'role_id',
        'folder_id',
        'permission',
        'modifier_id',
        'notification_type_id',
    ];

    protected $primaryKey = ['role_id', 'folder_id'];
    public $incrementing = false;

    protected $casts = [
        'permission' => FolderPermissionType::class,
    ];

    // Optionally disable logging of empty attributes.
    public function attributeValuesToBeLogged(): array
    {
//        $attributes = parent::attributeValues();
        $attributes = $this->getAttributes();

        return array_filter($attributes, function ($value, $key) {
            return $key !== '';
        }, ARRAY_FILTER_USE_BOTH);
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

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * ログに記録する項目
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->useLogName('roleFolderPermission')
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
        $key = "activitylog.roleFolderPermission_{$eventName}";

        return trans($key);
    }

    /**
     * ログに記録する際のメッセージを取得
     */
    protected function getLogDescriptionForEvent(string $eventName): string
    {
        $key = "activitylog.default_message.roleFolderPermission_{$eventName}";

        // 言語ファイルにキーがあれば、言語ファイルから取得。なければ、デフォルト値を返す
        return Lang::has($key) ? trans($key) : "フォルダー権限またはフォルダー通知が{$eventName}されました";
    }

    public function delete()
    {
        // deletingイベントを手動で発火
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // 複合主キーを使ってレコードを削除
        $deleted = static::where('role_id', $this->role_id)
                         ->where('folder_id', $this->folder_id)
                         ->delete();

        // deletedイベントを手動で発火
        if ($deleted) {
            $this->fireModelEvent('deleted', false);
        }

        return $deleted;
    }
}