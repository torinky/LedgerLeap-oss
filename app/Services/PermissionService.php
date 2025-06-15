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
                            // より弱い権限を置き換える
                            $accessPermissions = $accessPermissions->filter(fn($p) => !$permType->includes($p));
                            $accessPermissions->push($permType);
                        }
                    }
                }
                $accessPermissions = $accessPermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder());

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

        // ロールIDでグループ化し、権限を結合（重複するロールがあった場合に権限をマージ）
        return $rolesWithPermissions->groupBy(fn($item) => $item->role->id) // ロールIDでグループ化
        ->map(function ($groupedItems) {
            $role = $groupedItems->first()->role;
            $uniquePermissions = collect();
            $sources = collect();
            $isInherited = false;

            foreach ($groupedItems as $item) {
                // 各パーミッションタイプとその包含関係を考慮してユニークな集合を形成
                foreach ($item->permissions as $perm) {
                    $added = false;
                    foreach ($uniquePermissions as $idx => $existingPerm) {
                        if ($existingPerm->includes($perm)) {
                            $added = true;
                            break;
                        }
                        if ($perm->includes($existingPerm)) {
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
                $sources->push($item->source);
                if ($item->is_inherited) $isInherited = true;
            }
            $uniquePermissions = $uniquePermissions->unique('value')->sortByDesc(fn($p) => $p->getOrder());

            return (object)[
                'role' => $role,
                'permissions' => $uniquePermissions,
                'source' => $sources->unique()->implode(', '),
                'is_inherited' => $isInherited
            ];
        })->values()->sortBy(fn($item) => $item->role->name);
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
                        $added = false;
                        foreach ($orgAccessPermissions as $idx => $existingPerm) {
                            if ($existingPerm->includes($perm)) {
                                $added = true;
                                break;
                            }
                            if ($perm->includes($existingPerm)) {
                                $orgAccessPermissions->forget($idx);
                                $orgAccessPermissions->push($perm);
                                $added = true;
                                break;
                            }
                        }
                        if (!$added) {
                            $orgAccessPermissions->push($perm);
                        }
                    }
                    $orgSources->push($accessItem->source);
                    if ($accessItem->is_inherited) $orgIsInherited = true;
                }
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
     * @return LengthAwarePaginator<User>
     */
    public function getAccessUsers(int $resourceId, string $resourceType, ?string $searchQuery = null): LengthAwarePaginator
    {
        // まず、対象リソースにアクセス権限を持つロールを取得する
        $accessRoles = $this->getAccessRolesWithPermissions($resourceId, $resourceType)
            ->filter(fn($item) => $item->permissions->isNotEmpty())
            ->pluck('role'); // ロールモデルのコレクション

        if ($accessRoles->isEmpty()) {
            return (new User())->newQuery()->paginate(10); // 空のPaginatorを返す
        }

        $query = User::query()->distinct();

        // ユーザーに直接割り当てられたロール、または所属組織に割り当てられたロールが
        // アクセス可能なロールに含まれるユーザーを取得
        $query->where(function (Builder $q) use ($accessRoles) {
            // ユーザーに直接割り当てられたロールが accessRoles に含まれる場合
            $q->whereHas('roles', function ($subQ) use ($accessRoles) {
                $subQ->whereIn('roles.id', $accessRoles->pluck('id'));
            })
                // ユーザーが所属する組織に割り当てられたロールが accessRoles に含まれる場合
                ->orWhereHas('organizations.roles', function ($subQ) use ($accessRoles) {
                    $subQ->whereIn('roles.id', $accessRoles->pluck('id'));
                });
        });

        // ユーザーの組織とロールをイーガーロードして N+1 問題を避ける
        $query->with(['organizations', 'roles']);

        // 各ユーザーに対して、直接のロールと組織からの継承ロールを分類して追加する
        // paginate() の前に with() でロードしておけば、後でアクセスする際にクエリが発生しない
        // Collection の map を使うと paginate の後で処理できる
        $users = $query->paginate(10);

        $users->getCollection()->transform(function ($user) {
            $user->display_organizations = $user->organizations; // 既存の表示に使用

            $directRoles = collect();
            $inheritedRoles = collect();

            // ユーザーに直接割り当てられたロールを分類
            $user->roles->each(function($role) use (&$directRoles) {
                $directRoles->push($role);
            });

            // 所属組織から継承されるロールを分類
            $user->organizations->each(function($org) use (&$inheritedRoles, $directRoles) {
                $org->getAllRoles()->diff($org->roles)->each(function($role) use (&$inheritedRoles, $directRoles) {
                    // 組織継承ロールがユーザーの直接ロールと重複しないようにする
                    if (!$directRoles->contains('id', $role->id)) {
                        $inheritedRoles->push($role);
                    }
                });
            });

            $user->categorized_roles = [
                'direct' => $directRoles->unique('id')->sortBy('name'),
                'inherited_from_organizations' => $inheritedRoles->unique('id')->sortBy('name'),
            ];
            return $user;
        });


        return $users;
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
            return FolderPermissionType::ADMIN;
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