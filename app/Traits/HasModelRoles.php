<?php

namespace App\Traits;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

trait HasModelRoles
{
    use HasRoles {
        HasRoles::roles as parentRoles;
    }

    public function assignRole(...$roles)
    {
        $roles = collect($roles)
            ->flatten()
            ->map(function ($role) {
                if (empty($role)) {
                    return false;
                }

                return $this->getStoredRole($role);
            })
            ->filter(function ($role) {
                return $role instanceof Role;
            })
            ->each(function ($role) {
                $this->ensureModelSharesGuard($role);
            })
            ->all();

        $this->roles()->saveMany($roles);

        $this->load('roles');

        return $this;
    }

    protected function getStoredRole($role): Role
    {
        if (is_string($role)) {
            return app(Role::class)->findByName($role, $this->getDefaultGuardName());
        }

        if (is_int($role)) {
            return app(Role::class)->findById($role, $this->getDefaultGuardName());
        }

        if ($role instanceof Role) {
            return $role;
        }

        throw RoleDoesNotExist::create($role);
    }

    public function roles(): MorphMany
    {
        return $this->morphMany(config('permission.models.role'), 'model');
    }
}
