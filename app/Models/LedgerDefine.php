<?php

namespace App\Models;

use App\Casts\AsColumnDefinesArrayJson;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;

/**
 * @method static find(Route|object|string|null $route)
 */
class LedgerDefine extends Model
{
    use HasFactory;

    protected $casts = [
        'column_define' => AsColumnDefinesArrayJson::class,
    ];

    protected $fillable = [
        'title', 'column_define', 'folder_id', 'creator_id', 'modifier_id',
    ];

    public function ledger()
    {
        return $this->hasMany(Ledger::class, 'ledger_define_id');
    }

    public function tag()
    {
        return $this->hasMany(Tag::class, 'ledger_define_id');
    }

    public function folder()
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'modifier_id');
    }

    public function scopeSearchTags($query, $keywords)
    {
        if (empty($keywords)) {
            return $query;
        }
        return $query->whereHas('tag', function ($query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $query->where('name', 'LIKE', '%' . $keyword . '%');
            }
        });
    }

}
