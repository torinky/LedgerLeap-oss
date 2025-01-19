<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function viewAny(User $user)
    {
        return $this->userService->hasPermission($user, ['view_roles', 'manage_roles']);
    }

    public function view(User $user)
    {
        return $this->userService->hasPermission($user, ['view_roles', 'manage_roles']);
    }

    public function create(User $user)
    {
        return $this->userService->hasPermission($user, ['create_roles', 'manage_roles']);
    }

    public function update(User $user)
    {
        return $this->userService->hasPermission($user, ['edit_roles', 'manage_roles']);
    }

    public function delete(User $user)
    {
        return $this->userService->hasPermission($user, ['delete_roles', 'manage_roles']);
    }
}
