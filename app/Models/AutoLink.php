<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AutoLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'pattern',
        'url_template',
        'description',
        'priority',
        'is_enabled',
        'open_in_new_tab',
        'creator_id',
        'modifier_id',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'open_in_new_tab' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (AutoLink $autoLink) {
            if (Auth::check()) {
                $autoLink->creator_id = Auth::id();
                $autoLink->modifier_id = Auth::id();
            }
        });

        static::updating(function (AutoLink $autoLink) {
            if (Auth::check()) {
                $autoLink->modifier_id = Auth::id();
            }
        });
    }

    public function scopes()
    {
        return $this->hasMany(AutoLinkScope::class);
    }

    public function scopeable()
    {
        return $this->morphedByMany(Folder::class, 'scopeable', 'auto_link_scopes');
    }

    public function folders()
    {
        return $this->morphedByMany(Folder::class, 'scopeable', 'auto_link_scopes');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }
}
