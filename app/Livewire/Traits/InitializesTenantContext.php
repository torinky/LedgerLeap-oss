<?php

namespace App\Livewire\Traits;

use Stancl\Tenancy\Tenancy;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request; // Request ファサードを追加

trait InitializesTenantContext
{
    public $tenantId;
    public function initializeTenantContext(Tenancy $tenancy): void
    {
        // 1. tenantIdがまだセットされていない場合（主に初回リクエスト時）、ルートから取得を試みる
        //    後続のLivewireリクエストでは、このプロパティに値が保持されているため、再取得は行われない。
        if (is_null($this->tenantId)) {
            $route = Request::route();
            if ($route) {
                // originalParameters() はルートモデルバインディング前の生のパラメータを返すため、
                // ライフサイクルの早い段階でも安定して値を取得できる。
                $this->tenantId = $route->originalParameters()['tenant'] ?? null;
                Log::info('InitializesTenantContext: tenantId from route parameters: ' . ($this->tenantId ?? 'null')); // 追加
            }
        }

        // 2. tenantIdが取得できていて、かつテナンシーが初期化されていない場合に初期化する
        if ($this->tenantId && !$tenancy->initialized) {
            $tenant = Tenant::find($this->tenantId); // find() を使う方が簡潔
            if ($tenant) {
                $tenancy->initialize($tenant);
                Log::info('Tenant re-initialized via InitializesTenantContext trait', ['tenant_id' => $this->tenantId]);
            } else {
                Log::error('Tenant not found for ID from property in InitializesTenantContext trait', ['tenant_id' => $this->tenantId]);
            }
        }
        Log::info('InitializesTenantContext: Final tenantId in trait: ' . ($this->tenantId ?? 'null')); // 追加
        Log::info('InitializesTenantContext: Tenancy initialized status: ' . ($tenancy->initialized ? 'true' : 'false')); // 追加
    }

    // Livewire の boot メソッドで呼び出す
    public function bootInitializesTenantContext(Tenancy $tenancy): void
    {
        $this->initializeTenantContext($tenancy);
    }
}
