<?php

namespace App\Models\Synonym;

use App\Casts\AsJson;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalTermGroup extends Model
{
    use HasFactory;

    protected $casts = [
        'synonyms' => AsJson::class,
    ];

    protected $fillable = [
        'synonyms', 'creator_id', 'modifier_id',
    ];

    public static function bootTechnicalWordGroup()
    {
        static::creating(function ($model) {
            if (empty($model->creator_id)) {
                $model->creator_id = auth()->id();
            }
        });

        static::updating(function ($model) {
            $model->modifier_id = auth()->id();
        });
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
}
