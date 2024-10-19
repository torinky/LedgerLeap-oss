<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class FolderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        return true;
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, Folder $folder)
    {
        return true;
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, Folder $folder)
    {
        return true;
        //
    }

    public function view(User $user, Folder $folder)
    {
//        return $user->hasPermissionTo('view_ledgers') || $folder->hasPermissionTo('view_ledgers');
    }

    public function create(User $user)
    {
        return true;
//        return $user->hasPermissionTo('manage_ledger_defines');
    }

    public function update(User $user, Folder $folder)
    {
        return true;
//        return $user->hasPermissionTo('edit_ledgers') || $folder->hasPermissionTo('edit_ledgers');
    }

    public function delete(User $user, Folder $folder)
    {
        return true;
//        return $user->hasPermissionTo('delete_ledgers') || $folder->hasPermissionTo('delete_ledgers');
    }
}
