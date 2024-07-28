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
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, LedgerDefine $ledgerDefine)
    {
        //
    }

    use HandlesAuthorization;

    public function view(User $user, LedgerDefine $ledgerDefine)
    {
        return $user->hasPermissionTo('view_ledgers') || $ledgerDefine->hasPermissionTo('view_ledgers');
    }

    public function create(User $user)
    {
        return $user->hasPermissionTo('manage_ledger_defines');
    }

    public function update(User $user, LedgerDefine $ledgerDefine)
    {
        return $user->hasPermissionTo('edit_ledgers') || $ledgerDefine->hasPermissionTo('edit_ledgers');
    }

    public function delete(User $user, LedgerDefine $ledgerDefine)
    {
        return $user->hasPermissionTo('delete_ledgers') || $ledgerDefine->hasPermissionTo('delete_ledgers');
    }
}
