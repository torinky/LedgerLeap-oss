<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SearchQuery extends Model
{
    protected $fillable = [
        'tenant_id',
        'query_text',
        'search_count',
        'user_count',
        'last_searched_at',
    ];

    protected $casts = [
        'search_count' => 'integer',
        'user_count' => 'integer',
        'last_searched_at' => 'datetime',
    ];

    public function words(): HasMany
    {
        return $this->hasMany(SearchQueryWord::class, 'query_id');
    }
}
