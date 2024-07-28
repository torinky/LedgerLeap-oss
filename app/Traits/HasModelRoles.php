<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Traits\HasRoles;

trait HasModelRoles
{
    use HasRoles {
        HasRoles::roles as parentRoles;
    }

    public function roles(): MorphToMany
    {
        return $this->morphToMany(
            config('permission.models.role'),
            'model',
            config('permission.table_names.model_has_roles'),
            config('permission.column_names.model_morph_key'),
            'role_id'
        );
    }

    // assignRole メソッドは HasRoles トレイトの実装をそのまま使用できます。
    // 必要に応じてカスタマイズすることも可能です。

    protected function getStoredRole($role): Role
    {
        if (is_string($role)) {
            return app(config('permission.models.role'))->findByName($role, $this->getDefaultGuardName());
        }

        if (is_int($role)) {
            return app(config('permission.models.role'))->findById($role, $this->getDefaultGuardName());
        }

        if ($role instanceof Role) {
            return $role;
        }

        throw RoleDoesNotExist::create($role);
    }
}
