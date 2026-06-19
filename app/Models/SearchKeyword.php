<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SearchKeyword extends Model
{
    protected $fillable = [
        'tenant_id',
        'keyword',
        'lemma',
        'reading',
        'pos',
        'pos_sub',
        'is_proper_noun',
        'search_count',
        'user_count',
        'last_searched_at',
    ];

    protected $casts = [
        'is_proper_noun' => 'boolean',
        'search_count' => 'integer',
        'user_count' => 'integer',
        'last_searched_at' => 'datetime',
    ];
}
