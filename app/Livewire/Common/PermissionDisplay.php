<?php

namespace App\Livewire\Common;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization;

// 追加
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PermissionDisplay extends Component
{
    use WithPagination;

    public int $resourceId;
    public string $resourceType; // 'Ledger', 'LedgerDefine', 'Folder'
//    public string $paginationTheme = 'mary';

    // アクセス可能なユーザーリストの検索/フィルタリング
    public ?string $searchUserQuery = null;
    public ?int $filterRoleId = null; // 未使用だが定義は残す
    public ?string $filterPermissionType = null; // 未使用だが定義は残す

    // ★★★ フィルタリング用プロパティ ★★★
    public ?int $filterByRoleId = null;
    public ?int $filterByOrganizationId = null;
    public ?string $filterByPermissionValue = '';

    // ★★★ フィルタ選択肢用プロパティ ★★★
    public Collection $roleOptions;
    public Collection $organizationOptions;
    public array $permissionOptions; // Enumから生成するためarray

    protected PermissionService $permissionService;

    protected $listeners = ['refreshPermissions' => '$refresh'];

    public function boot(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function mount(int $resourceId, string $resourceType): void
    {
        $this->resourceId = $resourceId;
        $this->resourceType = $resourceType;

        // ★★★ フィルタ選択肢を初期化 ★★★
        $this->roleOptions = Role::orderBy('name')->get(['id', 'name']);
        $this->organizationOptions = Organization::with('parent')->get()->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'full_name' => $org->full_name,
            ];
        });
        $this->permissionOptions = FolderPermissionType::asAccessSelectArray();
        $this->permissionOptions = array_map(function ($label, $value) {
            return [
                'value' => $value,
                'label' => $label,
            ];
        }, $this->permissionOptions, array_keys($this->permissionOptions));

    }


    /**
     * ロールを検索する
     */
    public function roleSearch(string $value = ''): void
    {
        $query = Role::orderBy('name');

        if ($value) {
            $query->where('name', 'like', "%{$value}%");
        }

        // 常に現在選択されているロールを検索結果に含める
        if ($this->filterByRoleId) {
            $query->orWhere('id', $this->filterByRoleId);
        }

        $this->roleOptions = $query->take(10)->get(['id', 'name']);
    }

    /**
     * 組織を検索する
     */
    public function organizationSearch(string $value = ''): void
    {
        $query = Organization::orderBy('name');

        if ($value) {
            $query->where('name', 'like', "%{$value}%");
        }

        // 常に現在選択されている組織を検索結果に含める
        if ($this->filterByOrganizationId) {
            $query->orWhere('id', $this->filterByOrganizationId);
        }
        $this->organizationOptions = $query->take(10)->get()->map(function ($org) {
            return [
                'id' => $org->id,
                'name' => $org->name,
                'full_name' => $org->full_name,
            ];
        });
    }

    /**
     * アクセス可能なロールと権限のリストを取得
     * @return Collection<object{role: Role, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool}>
     */
    /*    public function getAccessRolesProperty(): Collection
        {
            return $this->permissionService->getAccessRolesWithPermissions($this->resourceId, $this->resourceType);
        }*/
    /**
     * アクセス可能なロールと権限のリストを取得 (フィルタ適用)
     * @return Collection<object{...}>
     */
    public function getAccessRolesProperty(): Collection
    {
        $allRoles = $this->permissionService->getAccessRolesWithPermissions($this->resourceId, $this->resourceType);

        if ($this->filterByRoleId) {
            $allRoles = $allRoles->where('role.id', $this->filterByRoleId);
        }
        if ($this->filterByPermissionValue) {
            $allRoles = $allRoles->filter(function ($item) {
                return $item->permissions->contains(fn(FolderPermissionType $p) => $p->value === $this->filterByPermissionValue);
            });
        }

        return $allRoles;
    }

    /**
     * アクセス可能な組織と権限のリストを取得
     * @return Collection<object{organization: Organization, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool}>
     */
    /*    public function getAccessOrganizationsProperty(): Collection
        {
            return $this->permissionService->getAccessOrganizationsWithPermissions($this->resourceId, $this->resourceType);
        }*/
    /**
     * アクセス可能な組織と権限のリストを取得 (フィルタ適用)
     * @return Collection<object{...}>
     */
    public function getAccessOrganizationsProperty(): Collection
    {
        $allOrganizations = $this->permissionService->getAccessOrganizationsWithPermissions($this->resourceId, $this->resourceType);

        if ($this->filterByOrganizationId) {
            $allOrganizations = $allOrganizations->where('organization.id', $this->filterByOrganizationId);
        }
        if ($this->filterByPermissionValue) {
            $allOrganizations = $allOrganizations->filter(function ($item) {
                return $item->permissions->contains(fn(FolderPermissionType $p) => $p->value === $this->filterByPermissionValue);
            });
        }
        if ($this->filterByRoleId) {
            $allOrganizations = $allOrganizations->filter(function ($item) {
                return $item->direct_roles->contains('id', $this->filterByRoleId) || $item->inherited_roles->contains('id', $this->filterByRoleId);
            });
        }

        return $allOrganizations;
    }


    /**
     * アクセス可能なユーザーのリストを取得 (フィルタ適用)
     * @return LengthAwarePaginator<User>
     */
    #[Computed]
    public function getAccessUsersProperty(): LengthAwarePaginator
    {
        // フィルタ条件をサービスに渡すように変更
        return $this->permissionService->getAccessUsers(
            $this->resourceId,
            $this->resourceType,
            $this->searchUserQuery,
            $this->filterByRoleId,
            $this->filterByOrganizationId,
            $this->filterByPermissionValue
        );
    }

    /**
     * ログインユーザーの最高権限を取得
     * @return FolderPermissionType|null
     */
    public function getCurrentUserHighestPermissionProperty(): ?FolderPermissionType
    {
        return $this->permissionService->getCurrentUserHighestPermission($this->resourceId, $this->resourceType);
    }

    public function getCurrentUserAllPermissionsProperty(): ?array
    {
        return $this->permissionService->getCurrentUserAllPermissions($this->resourceId, $this->resourceType);
    }

    public function render()
    {
        // 権限表示の前提として、最低限の閲覧権限（例: view_folder や view_ledger_define, view_ledger）があるべき
        // ここではシンプルにログインしているか、または特別な全体権限を持っているかを見る
        if (!auth()->check()) {
            return view('livewire.common.permission-display-no-permission');
        }

        // TODO: より厳密な権限チェック
        // 例えば、当該リソースに対する view_access_permissions のような権限が必要であればここでチェック
        // 現状は、権限がないと getAccessRolesProperty() などが空を返すため、表示内容で制御

        return view('livewire.common.permission-display');
    }

    // ユーザーリストの検索クエリが変更された時にページネーションをリセット
    public function updatedSearchUserQuery(): void
    {
        $this->resetPage('accessUsersPage');
    }

    /**
     * フィルタが更新された際にページネーションをリセットする
     */
    public function updated($propertyName): void
    {
        if (in_array($propertyName, ['filterByRoleId', 'filterByOrganizationId', 'filterByPermissionValue'])) {
            $this->resetPage('accessUsersPage');
            $this->resetPage('accessOrganizationsPage');
            $this->resetPage('accessRolesPage');
        }
    }

    /**
     * フィルタをリセットする
     */
    public function resetFilters(): void
    {
        $this->reset(['filterByRoleId', 'filterByOrganizationId', 'filterByPermissionValue', 'searchUserQuery']);
        $this->resetPage();
    }


}