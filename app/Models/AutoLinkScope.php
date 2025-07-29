<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AutoLinkScope extends Model
{
    protected $table = 'auto_link_scopes';

    protected $fillable = [
        'auto_link_id',
        'scopeable_id',
        'scopeable_type',
    ];

    public function autoLink(): BelongsTo
    {
        return $this->belongsTo(AutoLink::class);
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }
}
