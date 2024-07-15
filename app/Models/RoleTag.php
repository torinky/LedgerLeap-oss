<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleTag extends Pivot
{
    public $incrementing = true;
    protected $table = 'role_tag';
    protected $fillable = [
        'role_id',
        'tag_id',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function tag()
    {
        return $this->belongsTo(Tag::class);
    }
}
