<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'role_tag')
            ->using(RoleTag::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
