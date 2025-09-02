<?php

namespace App\Livewire;

use App\Models\Folder;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TenantSwitcher extends Component
{
    public Collection $tenants;
    public ?Tenant $currentTenant;

    public function mount(): void
    {
        $this->currentTenant = tenant();

        // Tenancy::central() を使って、中央のコンテキストでテナント情報を取得
        tenancy()->central(function () {
            $allTenants = Tenant::all();
            $userTenantIds = Auth::user()->tenants->pluck('id')->toArray();

            $this->tenants = $allTenants->map(function ($tenant) use ($userTenantIds) {
                $tenant->is_member = in_array($tenant->id, $userTenantIds);
                $tenant->folders_tree = collect(); // デフォルトは空のコレクション

                // メンバーであるテナントについてのみフォルダ階層を取得
                if ($tenant->is_member) {
                    tenancy()->run($tenant, function () use ($tenant) {
                        // ユーザーが閲覧可能なルートフォルダを取得
                        // 現状は全フォルダを取得するが、将来的には権限を考慮する必要がある
                        $tenant->folders_tree = Folder::tree()->get()->toTree();
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