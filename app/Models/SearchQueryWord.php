<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchQueryWord extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'query_id',
        'word',
    ];

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(SearchQuery::class, 'query_id');
    }
}
