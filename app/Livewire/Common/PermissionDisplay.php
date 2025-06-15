<?php

namespace App\Livewire\Common;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Organization; // 追加
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService;
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
    }

    /**
     * アクセス可能なロールと権限のリストを取得
     * @return Collection<object{role: Role, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool}>
     */
    public function getAccessRolesProperty(): Collection
    {
        return $this->permissionService->getAccessRolesWithPermissions($this->resourceId, $this->resourceType);
    }

    /**
     * アクセス可能な組織と権限のリストを取得
     * @return Collection<object{organization: Organization, permissions: Collection<FolderPermissionType>, source: string, is_inherited: bool}>
     */
    public function getAccessOrganizationsProperty(): Collection
    {
        return $this->permissionService->getAccessOrganizationsWithPermissions($this->resourceId, $this->resourceType);
    }

    /**
     * アクセス可能なユーザーのリストを取得
     * @return LengthAwarePaginator<User>
     */
    public function getAccessUsersProperty(): LengthAwarePaginator
    {
        return $this->permissionService->getAccessUsers($this->resourceId, $this->resourceType, $this->searchUserQuery);
    }

    /**
     * ログインユーザーの最高権限を取得
     * @return FolderPermissionType|null
     */
    public function getCurrentUserHighestPermissionProperty(): ?FolderPermissionType
    {
        return $this->permissionService->getCurrentUserHighestPermission($this->resourceId, $this->resourceType);
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
}