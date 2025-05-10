<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Enums\WorkflowStatus;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\Organization;
use App\Models\Role;
use App\Models\RoleFolderPermission;
use App\Models\User;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mary\Traits\Toast;

class UserService
{
    use Toast;
    protected $writableFolderRepository;

    public function __construct(WritableFolderRepository $writableFolderRepository)
    {
        $this->writableFolderRepository = $writableFolderRepository;
    }

    /**
     * 指定されたユーザーに関連するすべての権限を取得し、組織からも権限を含めます。
     *
     * @param User $user 権限を取得するユーザー。
     * @return Collection 重複を取り除いた権限オブジェクトのコレクション。
     */
    public function getAllPermissionsForUser(User $user): Collection
    {
        $permissions = $user->permissions;

        foreach ($user->organizations as $organization) {
            $permissions = $permissions->merge($organization->getAllPermissions());
        }

        return $permissions->unique('id');
    }

    /**
     * ユーザーが指定された権限を持っているかどうかを判定する
     * 組織から継承した権限も考慮する
     *
     * @param string|string[] $permissionName
     */
    public function hasPermission(User $user, $permissionName): bool
    {
        $permissions = $this->getAllUniquePermissionsForUser($user);
        if (is_array($permissionName)) {
            //            dd($permissions->whereIn('name', $permissionName)->isNotEmpty());
            return $permissions->whereIn('name', $permissionName)->isNotEmpty();
        } else {
            return $permissions->contains('name', $permissionName);
        }
    }

    /**
     * 指定されたユーザーに関連するすべての役割を取得し、組織からも役割を含めます。
     *
     * @param User $user 役割を取得するユーザー。
     * @return Collection 重複を取り除いた役割オブジェクトのコレクション。
     */
    public function getAllRolesForUser(User $user): Collection
    {
        $roles = $user->roles;

        foreach ($user->organizations as $organization) {
            $roles = $roles->merge($organization->getAllRoles());
        }

        return $roles->unique('id');
    }

    /**
     * ユーザーが組織に対して特定の権限を持っているかどうかを確認します。
     * スーパー管理者、ユーザー自身の権限、ユーザーが所属する組織の権限も確認します。
     *
     * @param User $user 権限を確認するユーザー。
     * @param string $permission 確認する権限。
     * @param Organization $organization 権限を確認する組織。
     * @return bool ユーザーが権限を持っている場合は true、そうでない場合は false。
     */
    public function hasPermissionForOrganization(User $user, string $permission, Organization $organization): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($user->hasPermissionTo($permission)) {
            return true;
        }

        $organizationWithAncestors = $organization->ancestorsAndSelf()->pluck('id');
        $userOrganizations = $user->organizations()->whereIn('organizations.id', $organizationWithAncestors)->get();

        foreach ($userOrganizations as $org) {
            if ($org->hasPermissionTo($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ユーザーが組織に対して特定の役割を持っているかどうかを確認します。
     * スーパー管理者、ユーザー自身の役割、ユーザーが所属する組織の役割も確認します。
     *
     * @param User $user 役割を確認するユーザー。
     * @param string $role 確認する役割。
     * @param Organization $organization 役割を確認する組織。
     * @return bool ユーザーが役割を持っている場合は true、そうでない場合は false。
     */
    public function hasRoleForOrganization(User $user, string $role, Organization $organization): bool
    {
        if ($user->hasRole($role)) {
            return true;
        }

        $organizationWithAncestors = $organization->ancestorsAndSelf()->pluck('id');
        $userOrganizations = $user->organizations()->whereIn('organizations.id', $organizationWithAncestors)->get();

        foreach ($userOrganizations as $org) {
            if ($org->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ユーザーに組織に固有の役割を割り当てます。
     * 役割が文字列で渡された場合は、対応する役割オブジェクトを検索します。
     *
     * @param User $user 役割を割り当てるユーザー。
     * @param mixed $role 割り当てる役割。文字列か役割オブジェクトのいずれか。
     * @param Organization $organization 役割を割り当てる組織。
     */
    public function assignRoleToOrganization(User $user, $role, Organization $organization): void
    {
        if (is_string($role)) {
            $role = Role::findByName($role, 'web');
        }

        $user->roles()->attach($role->id, ['organization_id' => $organization->id]);
    }

    /**
     * ユーザーが組織に対して特定の役割を持っているかどうかを確認します。
     *
     * @param User $user 役割を確認するユーザー。
     * @param string $role 確認する役割。
     * @param Organization $organization 役割を確認する組織。
     * @return bool ユーザーが役割を持っている場合は true、そうでない場合は false。
     */
    public function hasRoleInOrganization(User $user, string $role, Organization $organization): bool
    {
        return $user->roles()
            ->where('name', $role)
            ->wherePivot('organization_id', $organization->id)
            ->exists();
    }

    /**
     * ユーザーに関連するすべての一意の権限を取得し、組織からも権限を含めます。
     * キャッシュを利用して、パフォーマンスを向上させる
     *
     * @param User $user 権限を取得するユーザー。
     * @return Collection 重複を取り除いた権限オブジェクトのコレクション。
     *
     * このメソッドは、ユーザーが持つすべての権限と、ユーザーが所属する組織が持つすべての権限を
     * 取得し、重複を取り除いたコレクションを返します。組織の権限は、組織の階層構造に従って取得されます。
     */
    public function getAllUniquePermissionsForUser(User $user): Collection
    {
        $cacheKey = "user:{$user->id}:all_permissions";

        return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user) {
            return $user->permissions->merge(
                $user->organizations->flatMap->getAllUniquePermissions()
            )->merge(
                $this->getAllUniqueRolesForUser($user)->flatMap->permissions
            )->unique('id');
        });
    }

    /**
     * ユーザーに関連する権限のキャッシュをクリアする
     */
    public function clearUserPermissionsCache(User $user): void
    {
        Cache::forget("user:{$user->id}:all_permissions");
    }

    /**
     * ユーザーに関連するすべての一意の役割を取得し、組織からも役割を含めます。
     *
     * @param User $user 役割を取得するユーザー。
     * @return Collection 重複を取り除いた役割オブジェクトのコレクション。
     */
    public function getAllUniqueRolesForUser(User $user): Collection
    {
        return $user->roles->merge(
            $user->organizations->flatMap->getAllRoles()
        )->unique('id');
    }
    // isManageableFolderForUser, isWritableFolderForUser, isReadableFolderForUser は
    // 新しい hasFolderPermission を使うように修正
    public function isManageableFolderForUser(User $user, Folder $folder): bool
    {
        return $this->hasFolderPermission($user, $folder, FolderPermissionType::ADMIN);
    }

    public function isWritableFolderForUser(User $user, Folder $folder): bool
    {
        return $this->hasFolderPermission($user, $folder, FolderPermissionType::WRITE);
    }

    public function isReadableFolderForUser(User $user, Folder $folder): bool
    {
        return $this->hasFolderPermission($user, $folder, FolderPermissionType::READ);
    }

//    public function isManageableFolderForUser(User $user, Folder $folder): bool
//    {
//        $manageableFolderIds = $this->writableFolderRepository->getManageableFolderIds($user, $folder);
//
//        return in_array($folder->id, $manageableFolderIds);
//    }
//
//    /**
//     * ユーザーが指定されたフォルダーに対して書き込み権限を持っているかどうかを判定する
//     */
//    public function isWritableFolderForUser(User $user, Folder $folder): bool
//    {
//        $writableFolderIds = $this->writableFolderRepository->getWritableFolderIds($user, $folder);
//
//        return in_array($folder->id, $writableFolderIds)
//            || $this->isManageableFolderForUser($user, $folder);
//    }
//
//    public function isReadableFolderForUser(User $user, Folder $folder): bool
//    {
//        $readableFolderIds = $this->writableFolderRepository->getReadableFolderIds($user, $folder);
//
//        return in_array($folder->id, $readableFolderIds)
//            || $this->isWritableFolderForUser($user, $folder)
//            || $this->isManageableFolderForUser($user, $folder);
//    }

    public function getNotifiableRoles(string $eventType, $eventSubject): Collection
    {
        // PoC では、常に「All Users」ロールを返す
        return Role::where('name', 'All Users')->get();
    }

    /**
     * 指定されたロール ID の配列に紐づくユーザーのコレクションを取得する
     */
    public function getUsersByRoleIds(array $roleIds): Collection
    {
        return User::whereHas('roles', function ($query) use ($roleIds) {
            $query->whereIn('id', $roleIds);
        })->get();
    }

    /**
     * 組織が持つすべてのロールを取得する (上位組織から継承されたロールも含む)
     */
    public function getAllRolesForOrganization(Organization $organization): Collection
    {
        $roles = $organization->roles; // 組織に直接紐づくロールを取得

        foreach ($organization->ancestors as $ancestor) {
            $roles = $roles->merge($ancestor->roles); // 上位組織のロールをマージ
        }

        return $roles->unique('id'); // 重複を除外して返す
    }

    /**
     * ユーザーが設定画面 (Filament など) にアクセスする権限を持っているか判定する
     *
     * @param User $user
     * @return bool
     */
    public function canUserAccessSettings(User $user): bool
    {
        // キャッシュを利用しても良いが、権限チェックの頻度による
        // $cacheKey = "user:{$user->id}:can_access_settings";
        // return Cache::remember($cacheKey, now()->addMinutes(60), function () use ($user) {

        // ユーザーが持つ全権限名を取得 (既存メソッド利用)
        $permissions = $this->getAllUniquePermissionsForUser($user)->pluck('name');

        // 設定画面アクセスに関連すると見なすキーワードや権限名
        $keywords = ['manage', 'create_', 'update_', 'delete_', 'restore_', 'force_delete_'];
        $specificPermissions = ['view_roles', 'view_permissions', 'view_activity_logs'];
        $subjects = ['roles', 'permissions', 'users', 'organizations', 'ledger_defines']; // 管理対象

        // いずれかの条件に一致するかチェック
        foreach ($permissions as $permission) {
            // 特定の権限リストに含まれるか
            if (in_array($permission, $specificPermissions)) {
                // Log::debug("User {$user->id} has specific permission: {$permission}"); // デバッグ用
                return true;
            }
            // キーワードのいずれかが前方一致するか
            foreach ($keywords as $keyword) {
                if (str_starts_with($permission, $keyword)) {
                    // Log::debug("User {$user->id} has keyword permission: {$permission}"); // デバッグ用
                    return true;
                }
            }
            // 特定の管理対象を含むか (より緩やかな判定)
            foreach ($subjects as $subject) {
                if (str_contains($permission, $subject)) { // str_contains で部分一致
                    // Log::debug("User {$user->id} has subject permission: {$permission}"); // デバッグ用
                    return true;
                }
            }
            // preg_match を使う場合
            // if (preg_match('/(' . implode('|', $subjects) . ')/', $permission)) {
            //     return true;
            // }
        }

        // 上記のいずれにも該当しなければ false
        // Log::debug("User {$user->id} cannot access settings."); // デバッグ用
        return false;

        // }); // キャッシュを使う場合の閉じ括弧
    }

    /**
     * ユーザーが特定のフォルダに対して指定されたアクセス権限を持っているか確認する (包含関係考慮)
     *
     * @param User $user 対象ユーザー
     * @param Folder $folder 対象フォルダ
     * @param FolderPermissionType $requiredPermission 必要な最低権限
     * @return bool
     */
    public function hasFolderPermission(User $user, Folder $folder, FolderPermissionType $requiredPermission): bool
    {
        // スーパー管理者は常に true
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // ユーザーが持つ全ロールIDを取得
        $roleIds = $this->getAllUniqueRolesForUser($user)->pluck('id')->toArray();
        if (empty($roleIds)) {
            return false;
        }

        // 対象フォルダとその祖先フォルダのIDリストを取得
        $folderIds = $folder->ancestorsAndSelf($folder->id)->pluck('id')->toArray();

        // キャッシュキー (ユーザーID、フォルダIDリストのハッシュ、要求権限で作成)
//        $folderIdsHash = md5(implode(',', $folderIds));
//        $cacheKey = "user:{$user->id}:folders:{$folderIdsHash}:perm:{$requiredPermission->value}";
//        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($roleIds, $folderIds, $requiredPermission) {

            // DB から該当ロールとフォルダ/祖先フォルダに紐づく権限を取得
            $permissions = RoleFolderPermission::whereIn('role_id', $roleIds)
                ->whereIn('folder_id', $folderIds)
                ->whereIn('permission', FolderPermissionType::accessPermissionValues()) // アクセス権限のみ対象
                ->pluck('permission'); // permission カラムの値 (Enum) の Collection を取得

            // 取得した権限の中に、要求された権限 or それより上位の権限が含まれているかチェック
            foreach ($permissions as $grantedPermission) {
                // $grantedPermission は FolderPermissionType Enum インスタンス
                if ($grantedPermission->includes($requiredPermission)) {
                    return true; // 包含関係を満たす権限が見つかった
                }
            }
            return false; // 条件を満たす権限が見つからなかった
//        }); // Cache::remember の閉じ括弧
    }


    /**
     * 指定されたフォルダに対して特定のアクセス権限を持つユーザーのリストを取得する
     *
     * @param Folder $folder
     * @param FolderPermissionType $requiredPermission
     * @param string $searchQuery 検索文字列 (オプション)
     * @return Collection<User>
     */
    public function getUsersWithFolderPermission(Folder $folder, FolderPermissionType $requiredPermission, string $searchQuery = ''): Collection
    {
        $folderIds = $folder->ancestorsAndSelf($folder->id)->pluck('id')->toArray();

        $roleIds = RoleFolderPermission::whereIn('folder_id', $folderIds)
            ->whereIn('permission', FolderPermissionType::accessPermissionValues())
            ->get(['role_id', 'permission'])
            ->filter(fn ($rp) => $rp->permission->includes($requiredPermission))
            ->pluck('role_id')
            ->unique()
            ->all();

        if(empty($roleIds)) {
            return collect();
        }

        // ユーザーを取得するクエリ
        $query = User::whereHas('roles', function ($q) use ($roleIds) {
            $q->whereIn('roles.id', $roleIds);
        });

        // 検索クエリがあればユーザー名でフィルタリング
        if (!empty($searchQuery)) {
            $query->where('name', 'like', "%{$searchQuery}%");
        }

        return $query->distinct()->orderBy('name')->get(); // 名前順で取得
    }

    /**
     * ユーザーが引き継ぎ可能なワークフロータスク (Ledger) のコレクションを取得する
     * (自分が担当者でも申請者でもない、権限のあるタスク)
     *
     * @param User $user 対象ユーザー
     * @return Collection<Ledger>
     */
    public function getClaimableTasks(User $user): Collection
    {
        $userId = $user->id;

        // 1. ユーザーが INSPECT または APPROVE 権限を持つフォルダの ID リストを取得
        $privilegedFolderIds = $this->getPrivilegedFolderIdsForClaimable($user); // ヘルパーメソッド化

        if ($privilegedFolderIds->isEmpty() && !$user->hasRole('Super Admin')) {
            return collect();
        }

        // 2. Ledger テーブルを検索
        $claimableLedgers = Ledger::whereIn('status', [
            WorkflowStatus::PENDING_INSPECTION->value,
            WorkflowStatus::PENDING_APPROVAL->value,
        ])
            // 自分が申請者ではない
            ->where('creator_id', '!=', $userId)
            // 最新の LedgerDiff を確認し、自分が担当者ではない
            ->where(function (Builder $query) use ($userId) {
                $query->whereDoesntHave('latestDiff', function (Builder $diffQuery) use ($userId) {
                    $diffQuery->where('inspector_id', $userId)
                        ->orWhere('approver_id', $userId);
                })
                    ->orWhereDoesntHave('latestDiff'); // 最新Diffがない場合も考慮 (DRAFT直後など)
            })
            // 権限のあるフォルダに紐づく LedgerDefine を持つ Ledger を対象
            ->when(!$user->hasRole('Super Admin'), function (Builder $query) use ($privilegedFolderIds) {
                $query->whereHas('define', function (Builder $defineQuery) use ($privilegedFolderIds) {
                    $defineQuery->whereIn('folder_id', $privilegedFolderIds);
                });
            })
            ->withNeededRelations() // Ledgerモデルのスコープを想定
            ->orderBy('updated_at', 'desc')
            ->get();

        // さらに、取得した各 Ledger が実際に引き継ぎ可能か（権限があるか）を最終確認
        return $claimableLedgers->filter(function (Ledger $ledger) use ($user) {
            if (!$ledger->define?->folder) return false; // フォルダがなければ権限判定不可
            $requiredPermission = ($ledger->status === WorkflowStatus::PENDING_INSPECTION)
                ? FolderPermissionType::INSPECT
                : FolderPermissionType::APPROVE;
            return $this->hasFolderPermission($user, $ledger->define->folder, $requiredPermission);
        });
    }

    /**
     * 引き継ぎ可能なタスクを検索するために、ユーザーが点検または承認権限を持つフォルダIDを取得
     */
    private function getPrivilegedFolderIdsForClaimable(User $user): Collection
    {
        $userRoles = $this->getAllUniqueRolesForUser($user);
        if ($userRoles->isEmpty()) {
            return collect();
        }

        $roleFolderPermissions = RoleFolderPermission::whereIn('role_id', $userRoles->pluck('id'))
            ->whereIn('permission', [ // INSPECT, APPROVE, ADMIN 権限を対象
                FolderPermissionType::INSPECT->value,
                FolderPermissionType::APPROVE->value,
                FolderPermissionType::ADMIN->value,
            ])
            ->distinct()
            ->pluck('folder_id');

        return Folder::whereIn('id', $roleFolderPermissions)
            ->with('descendants')
            ->get()
            ->flatMap(fn($folder) => $folder->descendantsAndSelf($folder->id)->pluck('id'))
            ->unique()
            ->values();
    }


}
