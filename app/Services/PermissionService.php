<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * 権限表示に関するロジックを提供するサービス
 */
class PermissionService
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * 指定されたリソースに対するアクセス可能なロールとその権限タイプを取得する
     *
     * @param int $resourceId
     * @param string $resourceType
     * @return Collection<object{role: Role, permissions: Collection<FolderPermissionType>, is_direct: bool}>
     */
    public function getAccessRolesWithPermissions(int $resourceId, string $resourceType): Collection
    {
        $rolesWithPermissions = collect();
        $targetFolder = null;
        $targetLedgerDefine = null;

        // 対象リソースに応じたフォルダと台帳定義を特定
        switch ($resourceType) {
            case 'Ledger':
                $ledger = Ledger::find($resourceId);
                if ($ledger) {
                    $targetLedgerDefine = $ledger->define;
                    $targetFolder = $targetLedgerDefine->folder;
                }
                break;
            case 'LedgerDefine':
                $targetLedgerDefine = LedgerDefine::find($resourceId);
                if ($targetLedgerDefine) {
                    $targetFolder = $targetLedgerDefine->folder;
                }
                break;
            case 'Folder':
                $targetFolder = Folder::find($resourceId);
                break;
            default:
                return $rolesWithPermissions; // 不明なリソースタイプ
        }

        // 1. フォルダの権限を取得
        if ($targetFolder) {
            $allRoles = Role::all(); // 全ロールを取得 (最適化の余地あり)
            foreach ($allRoles as $role) {
                $folderPermissions = $targetFolder->getAllPermissionsWithInheritance($role); // 包含関係考慮済み
                $accessPermissions = collect($folderPermissions)->filter(fn($p) => FolderPermissionType::tryFrom($p)?->isAccessType());

                if ($accessPermissions->isNotEmpty()) {
                    $rolesWithPermissions->push((object)[
                        'role' => $role,
                        'permissions' => $accessPermissions->map(fn($p) => FolderPermissionType::from($p)),
                        'source' => 'folder', // 権限のソース
                        'is_inherited' => $targetFolder->id !== $resourceId // 対象がフォルダ自身でなければ継承
                    ]);
                }
            }
        }

        // 2. 台帳定義に直接紐づくロールの権限を取得 (LedgerDefine または Ledger が対象の場合のみ)
        if ($targetLedgerDefine && in_array($resourceType, ['Ledger', 'LedgerDefine'])) {
            $ledgerDefineRoles = $targetLedgerDefine->roles;
            foreach ($ledgerDefineRoles as $role) {
                // HasModelRoles トレイトの hasPermissionTo() を使用
                $directPermissions = collect();
                foreach (FolderPermissionType::accessPermissions() as $permType) { // アクセス権限タイプを全て試す
                    if ($targetLedgerDefine->hasPermissionTo($permType->value, $role->name)) { // ロール名でチェック
                        $directPermissions->push($permType);
                    }
                }

                if ($directPermissions->isNotEmpty()) {
                    $rolesWithPermissions->push((object)[
                        'role' => $role,
                        'permissions' => $directPermissions,
                        'source' => 'ledger_define',
                        'is_inherited' => false // 台帳定義への直接割り当て
                    ]);
                }
            }
        }

        // ロールIDとソースでユニーク化し、権限を結合
        return $rolesWithPermissions->groupBy(fn($item) => $item->role->id . '_' . $item->source)
            ->map(function ($groupedItems) {
                $uniquePermissions = collect();
                foreach ($groupedItems as $item) {
                    // 各パーミッションタイプとその包含関係を考慮してユニークな集合を形成
                    foreach ($item->permissions as $perm) {
                        $added = false;
                        foreach ($uniquePermissions as $idx => $existingPerm) {
                            if ($existingPerm->includes($perm)) { // 既存のものが新しいものを含むなら何もしない
                                $added = true;
                                break;
                            }
                            if ($perm->includes($existingPerm)) { // 新しいものが既存のものを含むなら既存を置き換える
                                $uniquePermissions->forget($idx);
                                $uniquePermissions->push($perm);
                                $added = true;
                                break;
                            }
                        }
                        if (!$added) {
                            $uniquePermissions->push($perm);
                        }
                    }
                }
                // より強い権限が残るようにソート
                $uniquePermissions = $uniquePermissions->sortByDesc(fn($p) => $p->getOrder());

                return (object)[
                    'role' => $groupedItems->first()->role,
                    'permissions' => $uniquePermissions->unique('value'), // Enum値でさらにユニーク化
                    'source' => $groupedItems->first()->source,
                    'is_inherited' => $groupedItems->first()->is_inherited ?? false
                ];
            })->values()->sortBy(fn($item) => $item->role->name); // ロール名でソート
    }

    /**
     * 指定されたリソースにアクセス可能なユーザーのリストを取得する
     *
     * @param int $resourceId
     * @param string $resourceType
     * @param string|null $searchQuery
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getAccessUsers(int $resourceId, string $resourceType, ?string $searchQuery = null) // : LengthAwarePaginator
    {
        $accessRoles = $this->getAccessRolesWithPermissions($resourceId, $resourceType)
            ->filter(fn($item) => $item->permissions->isNotEmpty()) // アクセス権限を持つもののみ
            ->pluck('role'); // ロールモデルのコレクション

        if ($accessRoles->isEmpty()) {
            return collect(); // 誰もアクセスできない
        }

        $query = User::query()->distinct();

        // ロール経由でアクセスできるユーザー
        $query->whereHas('roles', function ($q) use ($accessRoles) {
            $q->whereIn('id', $accessRoles->pluck('id'));
        });

        // ユーザーが所属する組織を通してアクセスできるユーザー
        // これは複雑になるため、ここではロール経由のアクセスに絞るか、別途詳細なロジックが必要。
        // 現状、SpatieのPermissionTraitはユーザーと組織に直接ロールを付与できるため、
        // 上記の whereHas('roles') が、ユーザーに直接付与されたロールと、
        // ユーザーが所属する組織に付与されたロールの両方をカバーすることになる。
        // `UserService::getAllUniqueRolesForUser()` が既に組織のロールも考慮している。
        // ここでは `$accessRoles` に含まれるロールを持つユーザーをシンプルに取得する。


        if ($searchQuery) {
            $query->where('name', 'like', '%' . $searchQuery . '%')
                ->orWhere('email', 'like', '%' . $searchQuery . '%');
        }

        return $query->paginate(10);
    }


    /**
     * ログインユーザーが指定されたリソースに対して持つ最も強い権限を取得する
     *
     * @param int $resourceId
     * @param string $resourceType
     * @return FolderPermissionType|null
     */
    public function getCurrentUserHighestPermission(int $resourceId, string $resourceType): ?FolderPermissionType
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // スーパー管理者なら ADMIN を返す
        if ($user->hasRole('super_admin')) {
//            return FolderPermissionType::ADMIN;
        }

        $targetFolder = null;
        $targetLedgerDefine = null;

        switch ($resourceType) {
            case 'Ledger':
                $ledger = Ledger::find($resourceId);
                if ($ledger) {
                    $targetLedgerDefine = $ledger->define;
                    $targetFolder = $targetLedgerDefine->folder;
                }
                break;
            case 'LedgerDefine':
                $targetLedgerDefine = LedgerDefine::find($resourceId);
                if ($targetLedgerDefine) {
                    $targetFolder = $targetLedgerDefine->folder;
                }
                break;
            case 'Folder':
                $targetFolder = Folder::find($resourceId);
                break;
            default:
                return null;
        }

        $highestPermission = null;

        // 1. フォルダの権限 (継承含む)
        if ($targetFolder) {
            foreach (FolderPermissionType::accessPermissions() as $permissionType) {
                // ユーザーがこのフォルダに対してこの権限を持っているか（包含関係考慮済み）
                if ($this->userService->hasFolderPermission($user, $targetFolder, $permissionType)) {
                    if (!$highestPermission || $permissionType->getOrder() > $highestPermission->getOrder()) {
                        $highestPermission = $permissionType;
                    }
                }
            }
        }

        // 2. 台帳定義の直接権限 (LedgerDefine または Ledger が対象の場合のみ)
        if ($targetLedgerDefine && in_array($resourceType, ['Ledger', 'LedgerDefine'])) {
            // 台帳定義に直接割り当てられたロール経由の権限
            $userRoles = $this->userService->getAllUniqueRolesForUser($user);
            foreach ($userRoles as $userRole) {
                // hasPermissionTo は Spatie の permission system を使う
                // HasModelRoles トレイトの hasPermissionTo() は role name を受け取る
                foreach (FolderPermissionType::accessPermissions() as $permissionType) {
                    if ($targetLedgerDefine->hasPermissionTo($permissionType->value, $userRole->name)) {
                        if (!$highestPermission || $permissionType->getOrder() > $highestPermission->getOrder()) {
                            $highestPermission = $permissionType;
                        }
                    }
                }
            }
        }

        return $highestPermission;
    }
}