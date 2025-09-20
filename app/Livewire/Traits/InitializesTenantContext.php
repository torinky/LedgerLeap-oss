<?php

namespace App\Livewire\Traits;

use Livewire\Component;
use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request; // Request ファサードを追加

trait InitializesTenantContext
{
    public function initializeTenantContext(Tenancy $tenancy): void
    {
        if (!$tenancy->initialized) {
            $tenantId = Request::route('tenant'); // URLからテナントIDを取得
            if ($tenantId) {
                $tenant = Tenant::where('id', $tenantId)->first();
                if ($tenant) {
                    $tenancy->initialize($tenant);
                    Log::info('Tenant re-initialized via InitializesTenantContext trait', ['tenant_id' => $tenantId]);
                } else {
                    Log::error('Tenant not found for ID from route in InitializesTenantContext trait', ['tenant_id' => $tenantId]);
                    // エラーハンドリングは呼び出し元で行うか、ここでToastなどを表示
                }
            } else {
                Log::error('Tenant ID not found in route for InitializesTenantContext trait');
                // エラーハンドリング
            }
        }
    }

    // Livewire の boot メソッドで呼び出す
    public function bootInitializesTenantContext(Tenancy $tenancy): void
    {
        $this->initializeTenantContext($tenancy);
    }
}
