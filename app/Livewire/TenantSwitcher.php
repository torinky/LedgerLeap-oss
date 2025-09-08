<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\Tenant;
use App\Services\TenantAccessService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public Collection $tenants;
    public ?\App\Models\Tenant $currentTenant;

    public function mount(TenantAccessService $tenantAccessService): void
    {
        $this->currentTenant = tenant();

        // Tenancy::central() を使って、中央のコンテキストでテナント情報を取得
        tenancy()->central(function () use ($tenantAccessService) {
            $allTenants = Tenant::all();
            $accessibleTenantIds = $tenantAccessService->getAccessibleTenants(Auth::user())->pluck('id')->toArray();

            $this->tenants = $allTenants->map(function ($tenant) use ($accessibleTenantIds) {
                $tenant->is_member = in_array($tenant->id, $accessibleTenantIds);
                $tenant->folders_tree = collect(); // デフォルトは空のコレクション

                // メンバーであるテナントについてのみフォルダ階層を取得
                if ($tenant->is_member) {
                    $tenant->run(function () use ($tenant) {
                        // ユーザーが閲覧可能なルートフォルダを取得
                        // 現状は全フォルダを取得するが、将来的には権限を考慮する必要がある
                        $tenant->folders_tree = Folder::get()->toTree();
                    });
                }
                return $tenant;
            });
        });
    }

    public function render()
    {
        return view('livewire.tenant-switcher');
    }
}