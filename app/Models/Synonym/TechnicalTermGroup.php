<?php

namespace App\Models\Synonym;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicalTermGroup extends Model
{
    use HasFactory;

    protected $casts = [
        'synonyms' => 'json',
    ];
    protected $fillable = [
        'synonyms', 'creator_id', 'modifier_id'
    ];

    /*    protected function synonyms(): Attribute
        {
            return Attribute::make(
                get: fn ($value) => is_array($value) ? $value : [],
                set: function ($value) {
                    if (!is_array($value)) {
                        return [];
                    }
                    $collection= collect($value);
                    $value=$collection->pluck('synonym')->toArray();
                    return json_encode( array_values(array_unique(Arr::sort($value))));
                },
            );
        }*/

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
}
