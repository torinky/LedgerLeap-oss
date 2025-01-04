<?php

namespace App\Policies;

use App\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewAny($user)
    {
        return true;
        return $user->hasPermissionTo('view_roles');
    }

    public function view($user, Role $role)
    {
        return true;
        return $user->hasPermissionTo('view_roles') || $role->hasPermission('view_roles');
    }

    public function hasPermission($user, Role $role, $permission)
    {
        $userPermissions = $user->getPermissions();
        $rolePermissions = $role->getPermissions();

        $combinedPermissions = array_merge($userPermissions, $rolePermissions);

        return in_array($permission, $combinedPermissions);
    }

    public function create($user)
    {
        return $user->hasPermissionTo('create_roles');
    }

    public function update($user, Role $role)
    {
        return true;
        return $user->hasPermissionTo('edit_roles') || $role->hasPermission('edit_roles');
    }

    public function delete($user, Role $role)
    {
        return $user->hasPermissionTo('delete_roles') || $role->hasPermission('delete_roles');
    }
}
