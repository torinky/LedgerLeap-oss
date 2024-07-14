<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kalnoy\Nestedset\NodeTrait;
use Spatie\Permission\Traits\HasRoles;

class Organization extends Model
{
    use HasFactory, HasRoles, NodeTrait, SoftDeletes;

    protected $fillable = ['org_id', 'name', 'description', 'parent_id'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_organizations')->withPivot('is_primary');
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

    public function hasPermissionWithInheritance($permission)
    {
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        foreach ($this->ancestors as $ancestor) {
            if ($ancestor->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    public function hasRoleWithInheritance($role)
    {
        if ($this->hasRole($role)) {
            return true;
        }

        foreach ($this->ancestors as $ancestor) {
            if ($ancestor->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
