<?php

namespace App\Policies;

use App\Models\LedgerDefine;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class LedgerDefinePolicy
{
    use HandlesAuthorization;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Determine whether the user can view any ledger defines.
     *
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の閲覧権限があるか確認
        if ($this->userService->hasPermission($user, 'view_ledger_defines')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the ledger define.
     *
     * @return Response|bool
     */
    public function view(User $user, LedgerDefine $ledgerDefine)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の閲覧権限があるか確認
        if ($this->userService->hasPermission($user, 'view_ledger_defines')) {
            // さらに、フォルダが閲覧可能かどうかも確認
            if ($this->userService->isReadableFolderForUser($user, $ledgerDefine->folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create ledger defines.
     *
     * @return Response|bool
     */
    public function create(User $user)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の作成権限があるか確認
        if ($this->userService->hasPermission($user, 'create_ledger_defines')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the ledger define.
     *
     * @return Response|bool
     */
    public function update(User $user, LedgerDefine $ledgerDefine)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の更新権限があるか確認
        if ($this->userService->hasPermission($user, 'manage_ledger_defines')) {
            // さらに、対象のフォルダが管理可能かどうかも確認
            if ($this->userService->isManageableFolderForUser($user, $ledgerDefine->folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the ledger define.
     *
     * @return Response|bool
     */
    public function delete(User $user, LedgerDefine $ledgerDefine)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の削除権限があるか確認
        if ($this->userService->hasPermission($user, 'delete_ledger_defines')) {
            // さらに、対象のフォルダが管理可能かどうかも確認
            if ($this->userService->isManageableFolderForUser($user, $ledgerDefine->folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can restore the ledger define.
     *
     * @return Response|bool
     */
    public function restore(User $user, LedgerDefine $ledgerDefine)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の復元権限があるか確認
        if ($this->userService->hasPermission($user, 'restore_ledger_defines')) {
            // さらに、対象のフォルダが管理可能かどうかも確認
            if ($this->userService->isManageableFolderForUser($user, $ledgerDefine->folder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the ledger define.
     *
     * @return Response|bool
     */
    public function forceDelete(User $user, LedgerDefine $ledgerDefine)
    {
        // ユーザーが所属する組織の権限も含めて、台帳定義の完全削除権限があるか確認
        if ($this->userService->hasPermission($user, 'force_delete_ledger_defines')) {
            // さらに、対象のフォルダが管理可能かどうかも確認
            if ($this->userService->isManageableFolderForUser($user, $ledgerDefine->folder)) {
                return true;
            }
        }

        return false;
    }

    public function ledgerView(User $user, LedgerDefine $ledgerDefine)
    {
        if (!$this->userService->hasPermission($user, 'view_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }

        return $this->userService->isReadableFolderForUser($user, $folder);
    }

    public function ledgerCreate(User $user, LedgerDefine $ledgerDefine): bool
    {
        //        dd('LedgerPolicy@create called');
        if (!$this->userService->hasPermission($user, 'create_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }

        return $this->userService->isWritableFolderForUser($user, $folder);
    }

    public function ledgerUpdate(User $user, LedgerDefine $ledgerDefine): bool
    {
        if (!$this->userService->hasPermission($user, 'edit_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }

        return $this->userService->isWritableFolderForUser($user, $folder);
    }

    public function ledgerDelete(User $user, LedgerDefine $ledgerDefine): bool
    {
        if (!$this->userService->hasPermission($user, 'delete_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }

        return $this->userService->isWritableFolderForUser($user, $folder);
    }

    public function ledgerRestore(User $user, LedgerDefine $ledgerDefine): bool
    {
        if (!$this->userService->hasPermission($user, 'restore_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }

        return $this->userService->isWritableFolderForUser($user, $folder);
    }

    public function ledgerForceDelete(User $user, LedgerDefine $ledgerDefine): bool
    {
        if (!$this->userService->hasPermission($user, 'delete_ledgers')) {
            return false;
        }
        $folder = $ledgerDefine->folder;

        if (!$folder) {
            return false;
        }

        return $this->userService->isWritableFolderForUser($user, $folder);
    }





}
