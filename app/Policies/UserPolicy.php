<?php

namespace App\Policies;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    private UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->userService->hasPermission($user, ['view_users', 'manage_users']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $this->userService->hasPermission($user, ['view_users', 'manage_users']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->userService->hasPermission($user, ['create_users', 'manage_users']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $this->userService->hasPermission($user, ['update_users', 'manage_users']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model = null): bool
    {
        return $this->userService->hasPermission($user, ['delete_users', 'manage_users']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->userService->hasPermission($user, ['restore_users', 'manage_users']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $this->userService->hasPermission($user, ['force_delete_users', 'manage_users']);
    }
}
