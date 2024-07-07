<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Permission\Traits\HasRoles;


class Organization extends Model
{
    use HasFactory, SoftDeletes, NodeTrait, HasRoles;

    protected $fillable = ['org_id', 'name', 'description', 'parent_id'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_organizations');
    }

    // 親組織から継承された権限を含む全ての権限を取得
    public function getAllPermissions()
    {
        $allPermissions = $this->permissions;

        foreach ($this->ancestors as $ancestor) {
            $allPermissions = $allPermissions->merge($ancestor->permissions);
        }

        return $allPermissions->unique('id');
    }

    // 親組織から継承された役割を含む全ての役割を取得
    public function getAllRoles()
    {
        $allRoles = $this->roles;

        foreach ($this->ancestors as $ancestor) {
            $allRoles = $allRoles->merge($ancestor->roles);
        }

        return $allRoles->unique('id');
    }
}
