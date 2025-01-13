<?php

namespace App\Observers;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class UserPermissionsObserver
{
    /**
     * Handle events after all transactions are committed.
     *
     * @var bool
     */
    public $afterCommit = true;

    /**
     * Handle the User "deleted" event.
     *
     * @param User $user
     * @return void
     */
    public function deleted(User $user)
    {
        $this->clearPermissionsCache($user);
    }

    /**
     * Clear the permissions cache for the given user.
     *
     * @param User $user
     * @return void
     */
    protected function clearPermissionsCache(User $user)
    {
        Cache::forget("user:{$user->id}:all_permissions");
    }

    /**
     * Handle the Role "updated" event.
     *
     * @param Role $role
     * @return void
     */
    public function roleUpdated(Role $role)
    {
        $this->clearPermissionsCacheForRole($role);
    }

    /**
     * Clear the permissions cache for users associated with the given role.
     *
     * @param Role $role
     * @return void
     */
    protected function clearPermissionsCacheForRole(Role $role)
    {
        $role->users()->each(function ($user) {
            $this->clearPermissionsCache($user);
        });
    }

    /**
     * Handle the Role "deleted" event.
     *
     * @param Role $role
     * @return void
     */
    public function roleDeleted(Role $role)
    {
        $this->clearPermissionsCacheForRole($role);
    }

    /**
     * Handle the Organization "updated" event.
     *
     * @param Organization $organization
     * @return void
     */
    public function organizationUpdated(Organization $organization)
    {
        $this->clearPermissionsCacheForOrganization($organization);
    }

    /**
     * Clear the permissions cache for users associated with the given organization.
     *
     * @param Organization $organization
     * @return void
     */
    protected function clearPermissionsCacheForOrganization(Organization $organization)
    {
        $organization->users()->each(function ($user) {
            $this->clearPermissionsCache($user);
        });
    }

    /**
     * Handle the Organization "deleted" event.
     *
     * @param Organization $organization
     * @return void
     */
    public function organizationDeleted(Organization $organization)
    {
        $this->clearPermissionsCacheForOrganization($organization);
    }
}
