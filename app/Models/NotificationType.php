<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'model',
        'route',
        'folder_relation',
        'event',
        'default_notify',
        'enabled',
    ];

    /**
     * Get the related folder for this notification type.
     *
     * @return mixed
     */
    public function folder()
    {
        if (! $this->folder_relation) {
            return null;
        }

        // リレーションシップパスを使ってフォルダーを取得
        return data_get($this, $this->folder_relation);
    }

    /**
     * Check if this notification type is for the given model.
     *
     * @param  mixed  $model
     */
    public function isForModel($model): bool
    {
        return $this->model === get_class($model);
    }
}
