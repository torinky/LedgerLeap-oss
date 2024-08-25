<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class Role extends SpatieRole
{
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'role_tag')
            ->using(RoleTag::class);
    }

    /*    public function model(): MorphTo
        {
            return $this->morphTo();
        }*/

    public function organizations()
    {
        return $this->morphedByMany(Organization::class, 'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }

    public function folders()
    {

        return $this->morphedByMany(\App\Models\Folder::class, 'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }
}
