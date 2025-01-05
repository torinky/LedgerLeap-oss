<?php

namespace App\Policies;

use App\Models\Folder;
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

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_ledgers');
    }

    public function view(User $user, LedgerDefine $ledgerDefine): bool
    {
        $folder = $ledgerDefine->folder;
        // ユーザーが view_ledgers 権限を持っていても、読み取り可能フォルダ範囲でなければ false を返す
        if ($user->hasPermissionTo('view_ledgers') &&
            $this->userService->isReadableFolderForUser($user, $folder)) {
            return true;
        }
        return false;
    }

    public function create(User $user, LedgerDefine $ledgerDefine): bool
    {
//        dd('LedgerPolicy@create called');
        if (!$user->hasPermissionTo('create_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }
        return $this->userService->isWritableFolderForUser($user, $folder);
    }

    public function update(User $user, LedgerDefine $ledgerDefine): bool
    {
        if (!$user->hasPermissionTo('edit_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }
        return $this->userService->isWritableFolderForUser($user, $folder);
    }

    public function delete(User $user, LedgerDefine $ledgerDefine): bool
    {
        $folder = $ledgerDefine->folder;
        return $user->hasPermissionTo('delete_ledgers') && $this->userService->isWritableFolderForUser($user, $folder);
    }
    /**
     * Determine whether the user can restore the model.
     *
     * @return Response|bool
     */
    public function restore(User $user, Ledger $ledger)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, Ledger $ledger)
    {
        //
    }
}
