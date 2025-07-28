<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'open_in_new_tab' => 'boolean',
    ];

    public function scopeable()
    {
        return $this->morphedByMany(Folder::class, 'scopeable', 'auto_link_scopes');
    }
}