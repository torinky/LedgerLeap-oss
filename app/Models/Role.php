<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'role_tag')
            ->using(RoleTag::class);
    }
}
