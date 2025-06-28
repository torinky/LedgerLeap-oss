<?php

namespace App\Services;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
     * (変更なし)
     *
     * @param int $resourceId
     * @param string $resourceType
     * @return Collection<object{role: Role, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool}>
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
                // Folder の getAllPermissionsWithInheritance を利用して、そのロールが持つ権限を継承含めて取得
                // ここで取得されるのは `permission.value` の文字列配列
                $folderPermissionsValues = $targetFolder->getAllPermissionsWithInheritance($role);
                $accessPermissions = collect();

                // 取得した文字列値を FolderPermissionType Enum に変換し、アクセスタイプのみをフィルタリング
                foreach ($folderPermissionsValues as $permValue) {
                    $permType = FolderPermissionType::tryFrom($permValue);
                    if ($permType && $permType->isAccessType()) {
                        // 既に同じ強度の権限が追加されていないか確認し、重複を防ぐ
                        $foundStrongerOrEqual = $accessPermissions->contains(fn($p) => $p->getOrder() >= $permType->getOrder());
                        if (!$foundStrongerOrEqual) {
                            $accessPermissions->push($permType);
                        }
                    }
                }
                $accessPermissions = $accessPermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder());
//                dd($accessPermissions);

                if ($accessPermissions->isNotEmpty()) {
                    $rolesWithPermissions->push((object)[
                        'role' => $role,
                        'permissions' => $accessPermissions,
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
                $directPermissions = collect();
                // HasModelRoles トレイトの hasPermissionTo() を使用して、台帳定義に直接紐づく権限をチェック
                foreach (FolderPermissionType::accessPermissions() as $permType) {
                    if ($targetLedgerDefine->hasPermissionTo($permType->value, $role->name)) {
                        $directPermissions->push($permType);
                    }
                }

                if ($directPermissions->isNotEmpty()) {
                    $rolesWithPermissions->push((object)[
                        'role' => $role,
                        'permissions' => $directPermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder()),
                        'source' => 'ledger_define',
                        'is_inherited' => false // 台帳定義への直接割り当て
                    ]);
                }
            }
        }
        return $rolesWithPermissions;
    }

    /**
     * 指定されたリソースにアクセス可能な組織とその権限タイプを取得する
     *
     * @param int $resourceId
     * @param string $resourceType
     * @return Collection<object{organization: Organization, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool, direct_roles: Collection<Role>, inherited_roles: Collection<Role>}>
     */
    public function getAccessOrganizationsWithPermissions(int $resourceId, string $resourceType): Collection
    {
        $organizationsWithPermissions = collect();

        // 1. 対象リソースにアクセス権限を持つロールを取得
        $accessRoles = $this->getAccessRolesWithPermissions($resourceId, $resourceType)
            ->filter(fn($item) => $item->permissions->isNotEmpty());

        if ($accessRoles->isEmpty()) {
            return collect();
        }

        // アクセス権限を持つロールのIDリスト
        $accessRoleIds = $accessRoles->pluck('role.id')->unique()->toArray();

        // 2. アクセス可能なロールに紐づく組織を効率的に取得
        //    直接そのロールを持つ組織、またはその組織の子孫組織
        $relevantOrganizations = Organization::whereHas('roles', function (Builder $query) use ($accessRoleIds) {
            $query->whereIn('roles.id', $accessRoleIds);
        })->with('ancestors', 'roles')->get(); // 祖先と直接ロールをイーガーロード

        // 関連する全ての組織とその祖先を結合し、ツリーを構築
        $allRelevantOrganizations = $relevantOrganizations->flatMap(fn($org) => $org->ancestors->push($org))->unique('id');

        // ツリー構造を再構築 (nestedsetのchildrenリレーションが正しくロードされるように)
        $rootOrganizations = $allRelevantOrganizations->whereNull('parent_id');
        $this->buildOrganizationTree($rootOrganizations, $allRelevantOrganizations);

        // 4. 各関連組織に対して、最終的な権限とロール情報を計算
        foreach ($allRelevantOrganizations as $org) {
            $orgAccessPermissions = collect();
            $orgSources = collect();
            $orgIsInherited = false;

            // この組織が持つ全てのロール（直接付与されたロールと親組織から継承されたロール）
            $orgAllRoles = $org->getAllRoles();
            $orgDirectRoles = $org->getDirectRoles(); // 直接のロール
            $orgInheritedRoles = $org->getInheritedRoles(); // 継承ロール

            foreach ($accessRoles as $accessItem) {
                // この組織がアクセス可能なロールのIDを持っているか
                if ($orgAllRoles->contains('id', $accessItem->role->id)) {
                    // アクセス権限をマージ
                    foreach ($accessItem->permissions as $perm) {
                        $orgAccessPermissions->push($perm);
                    }
                    $orgSources->push($accessItem->source);
                    if ($accessItem->is_inherited) $orgIsInherited = true;
                }
            }

            // 直接割り当てたロールと重複していたら除外
            if ($orgInheritedRoles->isNotEmpty()) {
                $orgInheritedRoles = $orgInheritedRoles->reject(function ($role) use ($orgDirectRoles) {
                    return $orgDirectRoles->contains('id', $role->id);
                })->values();
            }

            if ($orgAccessPermissions->isNotEmpty()) {
                $organizationsWithPermissions->push((object)[
                    'organization' => $org,
                    'permissions' => $orgAccessPermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder()),
                    'source' => $orgSources->unique()->implode(', '),
                    'is_inherited' => $orgIsInherited,
                    'direct_roles' => $orgDirectRoles->sortBy('name'), // 直接のロール
                    'inherited_roles' => $orgInheritedRoles->sortBy('name') // 継承ロール
                ]);
            }
        }

        // 組織名を階層表示のために整形してからソート
        return $organizationsWithPermissions->map(function($item) {
            $item->display_name = $item->organization->ancestors->pluck('name')->implode(' > ') . ($item->organization->ancestors->isNotEmpty() ? ' > ' : '') . $item->organization->name;
            return $item;
        })->sortBy('display_name')->values();
    }

    /**
     * 組織コレクションからツリーを構築するヘルパー
     * （NestedSet モデルの children リレーションが正しくロードされていない場合に対応）
     *
     * @param Collection $nodes
     * @param Collection $allOrganizations
     * @return void
     */
    private function buildOrganizationTree(Collection $nodes, Collection $allOrganizations): void
    {
        foreach ($nodes as $node) {
            $node->setRelation('children', $allOrganizations->filter(fn($org) => $org->parent_id === $node->id)->values());
            $this->buildOrganizationTree($node->children, $allOrganizations);
        }
    }


    /**
     * 指定されたリソースにアクセス可能なユーザーのリストを取得する
     *
     * @param int $resourceId
     * @param string $resourceType
     * @param string|null $searchQuery
     * @param int|null $filterByRoleId
     * @param int|null $filterByOrganizationId
     * @param string|null $filterByPermissionValue
     * @return LengthAwarePaginator<User>
     */
    public function getAccessUsers(
        int     $resourceId,
        string  $resourceType,
        ?string $searchQuery = null,
        ?int    $filterByRoleId = null,
        ?int    $filterByOrganizationId = null,
        ?string $filterByPermissionValue = ''
    ): LengthAwarePaginator
    {
        $resolved = $this->resolveTargetFolderAndLedgerDefine($resourceId, $resourceType);
        $targetFolder = $resolved['folder'];

        // まず、対象リソースにアクセス権限を持つロールと権限の情報を取得
        $accessItems = $this->getAccessRolesWithPermissions($resourceId, $resourceType)
            ->filter(fn($item) => $item->permissions->isNotEmpty());

        // ★★★ 権限タイプでフィルタリング ★★★
        if ($filterByPermissionValue) {
            $accessItems = $accessItems->filter(function ($item) use ($filterByPermissionValue) {
                return $item->permissions->contains(fn(FolderPermissionType $p) => $p->value === $filterByPermissionValue);
            });
        }
        // ★★★ ロールでフィルタリング ★★★
        if ($filterByRoleId) {
            $accessItems = $accessItems->where('role.id', $filterByRoleId);
        }

        // フィルタリング後のロールIDリストを取得
        $accessRoleIds = $accessItems->pluck('role.id')->unique()->filter()->all();

        // アクセス可能なロールがない場合は、ユーザーもいないので空の結果を返す
        if (empty($accessRoleIds)) {
            return (new User())->newQuery()->whereRaw('1 = 0')->paginate(10);
        }

        $query = User::query()->distinct();

        if ($filterByRoleId) {
            // 指定ロールを直接持つユーザー取得
            $query
                ->whereHas('roles', function ($q) use ($filterByRoleId) {
                    $q->where('roles.id', $filterByRoleId);
                })
                ->with(['organizations.ancestors', 'roles']);
        }

        // ★★★ 組織IDでフィルタリングする場合 ★★★
        if ($filterByOrganizationId) {
            // 組織とその子孫組織に所属するユーザーをフィルタリング対象とする
            $organization = Organization::find($filterByOrganizationId);
            if ($organization) {
                $orgIds = $organization->descendantsAndSelf($organization->id)->pluck('id');
                $query->whereHas('organizations', function ($q) use ($orgIds) {
                    $q->whereIn('organizations.id', $orgIds);
                });
            } else {
                // 存在しない組織IDが指定された場合は結果を0件にする
                $query->whereRaw('1 = 0');
            }
        }

        // ユーザーに直接割り当てられたロール、または所属組織（とその祖先）に割り当てられたロールが
        // アクセス可能なロールに含まれるユーザーを取得
        $query->where(function (Builder $q) use ($accessRoleIds) {
            // ユーザーに直接割り当てられたロール
            $q->whereHas('roles', function ($subQ) use ($accessRoleIds) {
                $subQ->whereIn('roles.id', $accessRoleIds);
            });

            // ユーザーが所属する組織（とその祖先）に割り当てられたロール
            $q->orWhereHas('organizations', function ($orgQuery) use ($accessRoleIds) {
                // 組織自身が持つロール
                $orgQuery->whereHas('roles', function ($roleQuery) use ($accessRoleIds) {
                    $roleQuery->whereIn('roles.id', $accessRoleIds);
                });
                // 組織の祖先が持つロール
                $orgQuery->orWhereHas('ancestors.roles', function ($roleQuery) use ($accessRoleIds) {
                    $roleQuery->whereIn('roles.id', $accessRoleIds);
                });
            });
        });

        // ユーザーの組織とロールをイーガーロードして N+1 問題を避ける
        $query->with(['organizations.ancestors', 'roles']);

        if ($searchQuery) {
            $query->where(function ($q) use ($searchQuery) {
                $q->where('name', 'like', '%' . $searchQuery . '%')
                    ->orWhere('email', 'like', '%' . $searchQuery . '%');
            });
        }

        // ★★★ ユーザーのロールでフィルタリング ★★★
        if ($filterByRoleId) {
            $directRoleUsers= User::query()->with('roles')->where('role.id', $filterByRoleId);
        }

        $users = $query->paginate(10);




        $users->getCollection()->transform(function ($user) use ($targetFolder,$filterByRoleId) {
            // ユーザーの直接ロール
            $directRoles = $user->roles->keyBy('id');

//            dd($directRoles);

            // ユーザーが組織から継承するロール
            $inheritedRoles = collect();
            $user->organizations->each(function ($org) use (&$inheritedRoles, $directRoles) {
                // 組織が持つ全ロール（直接＋継承）を取得
                $org->getAllRoles()->each(function ($role) use (&$inheritedRoles, $directRoles) {
                    // ユーザーの直接ロールと重複せず、まだ追加されていないロールを追加
                    if (!$directRoles->has($role->id) && !$inheritedRoles->has($role->id)) {
                        $inheritedRoles->put($role->id, $role);
                    }
                });
            });

            $user->categorized_roles = [
                'direct' => $directRoles->sortBy('name')->values(),
                'inherited_from_organizations' => $inheritedRoles->sortBy('name')->values(),
            ];

            // 権限の計算
            $directPermissions = collect();
            $inheritedPermissions = collect();
            if ($targetFolder) {
                // 直接ロールに紐づく権限
                foreach ($user->categorized_roles['direct'] as $role) {
                    $permValues = $targetFolder->getAllPermissionsWithInheritance($role);
                    foreach ($permValues as $permValue) {
                        $permType = FolderPermissionType::tryFrom($permValue);
                        if ($permType && $permType->isAccessType()) {
                            $directPermissions->push($permType);
                        }
                    }
                }
                // 継承ロールに紐づく権限
                foreach ($user->categorized_roles['inherited_from_organizations'] as $role) {
                    $permValues = $targetFolder->getAllPermissionsWithInheritance($role);
                    foreach ($permValues as $permValue) {
                        $permType = FolderPermissionType::tryFrom($permValue);
                        if ($permType && $permType->isAccessType()) {
                            $inheritedPermissions->push($permType);
                        }
                    }
                }
            }
            $user->categorized_permissions = [
                'direct' => $directPermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder()),
                'inherited_from_organizations' => $inheritedPermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder()),
            ];

            return $user;
        });

        return $users;
    }



    /**
     * 指定されたリソースID・タイプから対象のフォルダ・台帳定義を取得する共通処理
     *
     * @param int $resourceId
     * @param string $resourceType
     * @return array{folder: ?Folder, ledgerDefine: ?LedgerDefine}
     */
    private function resolveTargetFolderAndLedgerDefine(int $resourceId, string $resourceType): array
    {
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
                // 何もしない
                break;
        }

        return [
            'folder' => $targetFolder,
            'ledgerDefine' => $targetLedgerDefine,
        ];
    }

    /**
     * ログインユーザーが指定されたリソースに対して持つ最も強い権限を取得する
     * (変更なし)
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

        $resolved = $this->resolveTargetFolderAndLedgerDefine($resourceId, $resourceType);
        $targetFolder = $resolved['folder'];
        $targetLedgerDefine = $resolved['ledgerDefine'];

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

    /**
     * ログインユーザーが指定されたリソースに対して持つ全ての権限を取得する
     *
     * @param int $resourceId
     * @param string $resourceType
     * @return FolderPermissionType[]|null
     */
    public function getCurrentUserAllPermissions(int $resourceId, string $resourceType): ?array
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        // スーパー管理者なら全権限を返す
        if ($user->hasRole('super_admin')) {
//            return FolderPermissionType::cases();
        }

        $resolved = $this->resolveTargetFolderAndLedgerDefine($resourceId, $resourceType);
        $targetFolder = $resolved['folder'];
        $targetLedgerDefine = $resolved['ledgerDefine'];

        $permissions = [];

        // 1. フォルダの権限 (継承含む)
        if ($targetFolder) {
            foreach (FolderPermissionType::cases() as $permissionType) {
                if ($this->userService->hasFolderPermission($user, $targetFolder, $permissionType)) {
                    $permissions[$permissionType->value] = $permissionType;
                }
            }
        }

        // 2. 台帳定義の直接権限 (LedgerDefine または Ledger が対象の場合のみ)
        if ($targetLedgerDefine && in_array($resourceType, ['Ledger', 'LedgerDefine'])) {
            $userRoles = $this->userService->getAllUniqueRolesForUser($user);
            foreach ($userRoles as $userRole) {
                foreach (FolderPermissionType::cases() as $permissionType) {
                    if ($targetLedgerDefine->hasPermissionTo($permissionType->value, $userRole->name)) {
                        $permissions[$permissionType->value] = $permissionType;
                    }
                }
            }
        }

        return array_values($permissions);
    }
}