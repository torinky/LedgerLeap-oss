<?php

namespace App\Policies;

use App\Models\AutoLink;
use App\Models\User;

class AutoLinkPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('manage_auto_links');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AutoLink $autoLink): bool
    {
        return $user->can('manage_auto_links');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('manage_auto_links');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AutoLink $autoLink): bool
    {
        return $user->can('manage_auto_links');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AutoLink $autoLink): bool
    {
        return $user->can('manage_auto_links');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AutoLink $autoLink): bool
    {
        return $user->can('manage_auto_links');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AutoLink $autoLink): bool
    {
        return $user->can('manage_auto_links');
    }
}
