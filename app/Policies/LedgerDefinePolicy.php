<?php

namespace App\Policies;

use App\Models\LedgerDefine;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class LedgerDefinePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, LedgerDefine $ledgerDefine)
    {
        $userRoles = $user->roles;
        $folderRoles = $ledgerDefine->folder->roles;

        foreach ($userRoles as $userRole) {
            if ($folderRoles->contains($userRole)) {
                if ($userRole->hasPermissionTo('restore_ledger_defines')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, LedgerDefine $ledgerDefine)
    {
        $userRoles = $user->roles;
        $folderRoles = $ledgerDefine->folder->roles;

        foreach ($userRoles as $userRole) {
            if ($folderRoles->contains($userRole)) {
                if ($userRole->hasPermissionTo('force_delete_ledger_defines')) {
                    return true;
                }
            }
        }

        return false;
    }

    use HandlesAuthorization;

    public function view(User $user, LedgerDefine $ledgerDefine)
    {
        $userRoles = $user->roles;
        $folderRoles = $ledgerDefine->folder->roles;

        foreach ($userRoles as $userRole) {
            if ($folderRoles->contains($userRole)) {
                if ($userRole->hasPermissionTo('view_ledger_defines')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function create(User $user)
    {
        foreach ($user->roles as $role) {
            if ($role->hasPermissionTo('create_ledger_defines')) {
                return true;
            }
        }

        return false;
    }

    public function update(User $user, LedgerDefine $ledgerDefine)
    {
        $userRoles = $user->roles;
        $folderRoles = $ledgerDefine->folder->roles;

        foreach ($userRoles as $userRole) {
            if ($folderRoles->contains($userRole)) {
                if ($userRole->hasPermissionTo('update_ledger_defines')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function delete(User $user, LedgerDefine $ledgerDefine)
    {
        $userRoles = $user->roles;
        $folderRoles = $ledgerDefine->folder->roles;

        foreach ($userRoles as $userRole) {
            if ($folderRoles->contains($userRole)) {
                if ($userRole->hasPermissionTo('delete_ledger_defines')) {
                    return true;
                }
            }
        }

        return false;
    }
}
