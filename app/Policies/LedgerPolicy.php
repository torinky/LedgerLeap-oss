<?php

namespace App\Policies;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class LedgerPolicy
{
    use HandlesAuthorization;

    protected $userService;

    private LedgerDefinePolicy $ledgerDefinePolicy;

    public function __construct(UserService $userService, LedgerDefinePolicy $ledgerDefinePolicy)
    {
        $this->userService = $userService;
        $this->ledgerDefinePolicy = $ledgerDefinePolicy;
    }

    public function viewAny(User $user): bool
    {
        return $this->userService->hasPermission($user, 'view_ledgers');
    }

    public function view(User $user, Ledger $ledger): bool
    {
        return $this->ledgerDefinePolicy->ledgerView($user, $ledger->define);
    }

    public function create(User $user, LedgerDefine $ledgerDefine): bool
    {
        return $this->ledgerDefinePolicy->ledgerCreate($user, $ledgerDefine);
    }

    public function update(User $user, Ledger $ledger): bool
    {
        return $this->ledgerDefinePolicy->ledgerUpdate($user, $ledger->define);
    }

    public function delete(User $user, Ledger $ledger): bool
    {
        return $this->ledgerDefinePolicy->ledgerDelete($user, $ledger->define);
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, Ledger $ledger)
    {
        return $this->ledgerDefinePolicy->ledgerRestore($user, $ledger->define);
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, Ledger $ledger)
    {
        return $this->ledgerDefinePolicy->ledgerForceDelete($user, $ledger->define);
    }
}
