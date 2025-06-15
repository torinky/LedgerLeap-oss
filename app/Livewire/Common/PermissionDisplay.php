<?php

namespace App\Livewire\Common;

use App\Enums\FolderPermissionType;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Role;
use App\Models\User;
use App\Services\PermissionService; // PermissionService を使用
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
    public ?int $filterRoleId = null;
    public ?string $filterPermissionType = null; // FolderPermissionType の value

    protected PermissionService $permissionService;

    // リッスンするイベントを定義 (例: フィルタリング変更時など)
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
     * アクセス可能なユーザーのリストを取得
     * @return LengthAwarePaginator<User>
     */
    public function getAccessUsersProperty()
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
        // 例えば、当該フォルダへのREAD権限がない場合は表示しないなど
        // 現状は、権限がないと getAccessRolesProperty() などが空を返すため、表示内容で制御

        return view('livewire.common.permission-display');
    }

    // ユーザーリストの検索クエリが変更された時にページネーションをリセット
    public function updatedSearchUserQuery(): void
    {
        $this->resetPage('accessUsersPage');
    }
}