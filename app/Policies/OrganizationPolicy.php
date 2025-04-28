<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\Response;

class OrganizationPolicy
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
        return $this->userService->hasPermission($user, ['view_organizations', 'manage_organizations']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $this->userService->hasPermission($user, ['view_organizations', 'manage_organizations']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->userService->hasPermission($user, ['create_organizations', 'manage_organizations']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->userService->hasPermission($user, ['update_organizations', 'manage_organizations']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organization $organization = null): bool
    {
        return $this->userService->hasPermission($user, ['delete_organizations', 'manage_organizations']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Organization $organization): bool
    {
        return $this->userService->hasPermission($user, ['restore_organizations', 'manage_organizations']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Organization $organization): bool
    {
        return $this->userService->hasPermission($user, ['force_delete_organizations', 'manage_organizations']);
    }
}
