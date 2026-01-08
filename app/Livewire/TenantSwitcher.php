<?php

namespace App\Livewire;

use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Models\Tenant;
use App\Services\TenantAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class TenantSwitcher extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public Collection $tenants;

    public ?\App\Models\Tenant $currentTenant;

    public ?int $currentFolderId = null;

    public $selectedFolderIds;

    public bool $showFolders = true;

    public function mount(TenantAccessService $tenantAccessService): void
    {
        $this->currentTenant = tenant();
        $this->currentFolderId = request()->route('folderId');
        $this->initializeTenantsMenu($tenantAccessService);

    }

    #[On('permissions-changed')]
    public function refreshTenants(TenantAccessService $tenantAccessService)
    {
        $this->initializeTenantsMenu($tenantAccessService);
    }

    #[On('permissions-changed')]
    public function refresh(TenantAccessService $tenantAccessService)
    {
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

    public function initializeTenantsMenu(TenantAccessService $tenantAccessService): void
    {
        // Tenancy::central() を使って、中央のコンテキストでテナント情報を取得
        tenancy()->central(function () use ($tenantAccessService) {
            $allTenants = Tenant::all();
            $accessibleTenantIds = $tenantAccessService->getAccessibleTenants(Auth::user())->pluck('id')->toArray();

            $this->tenants = $allTenants->map(function ($tenant) use ($accessibleTenantIds) {
                $tenant->is_member = in_array($tenant->id, $accessibleTenantIds);
                $tenant->folders_tree = collect();

                if ($this->showFolders && $tenant->is_member) {
                    $tenant->run(function () use ($tenant) {
                        $tenant->folders_tree = Folder::get()->toTree()->toArray();
                    });
                }

                return $tenant;
            });
        });
    }
}
