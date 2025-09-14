<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\Tenant;
use App\Services\TenantAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public Collection $tenants;
    public ?\App\Models\Tenant $currentTenant;
    public ?int $currentFolderId = null;
    public $selectedFolderIds;

    public function mount(TenantAccessService $tenantAccessService): void
    {
        $this->currentTenant = tenant();
        $this->currentFolderId = request()->route('folderId');
        $this->initializeTenantsMenu($tenantAccessService);

    }

    #[On('currentFolderChangedByMain'), On('currentFolderChangedByTree')]
    public function changeCurrentFolder($newFolderId, $newSelectedFolderIds, TenantAccessService $tenantAccessService)
    {
        $this->currentFolderId = $newFolderId;
        $this->selectedFolderIds = $newSelectedFolderIds;
        $this->initializeTenantsMenu($tenantAccessService);
    }


    public function render()
    {
        return view('livewire.tenant-switcher');
    }

    /**
     * @param TenantAccessService $tenantAccessService
     * @return void
     */
    public function initializeTenantsMenu(TenantAccessService $tenantAccessService): void
    {
// Tenancy::central() を使って、中央のコンテキストでテナント情報を取得
        tenancy()->central(function () use ($tenantAccessService) {
            $allTenants = Tenant::all();
            $accessibleTenantIds = $tenantAccessService->getAccessibleTenants(Auth::user())->pluck('id')->toArray();

            $this->tenants = $allTenants->map(function ($tenant) use ($accessibleTenantIds) {
                $tenant->is_member = in_array($tenant->id, $accessibleTenantIds);
                $tenant->folders_tree = collect(); // デフォルトは空のコレクション

                // メンバーであるテナントについてのみフォルダ階層を取得
//                if ($tenant->is_member) {
                $tenant->run(function () use ($tenant) {
                    // ユーザーが閲覧可能なルートフォルダを取得
                    // 現状は全フォルダを取得するが、将来的には権限を考慮する必要がある
                    $tenant->folders_tree = Folder::get()->toTree()->toArray();
                });
//                }
                return $tenant;
            });
        });
    }
}