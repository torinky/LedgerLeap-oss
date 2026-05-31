<?php

namespace App\Livewire\Traits;

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Stancl\Tenancy\Tenancy; // Request ファサードを追加

/**
 * Livewire コンポーネントにテナントコンテキストの初期化と解決機能を提供するトレイト。
 *
 * コンポーネントの mount() または boot() でこのトレイトの initializeTenantContext()
 * を呼び出すことで、ルートパラメータやプロパティからテナントを特定し、
 * stancl/tenancy のコンテキストを復元する。
 * resolveTenantId() は Blade / URL ヘルパー向けのフォールバックチェーンを提供する。
 */
trait InitializesTenantContext
{
    public $tenantId;

    /**
     * 現在のテナントIDを解決する。
     *
     * 優先順位: プロパティ > グローバル tenancy() コンテキスト > 引数のフォールバック。
     * 主に Blade テンプレートや URL 生成のコンテキストで使用する。
     *
     * @param  string|int|null  $fallbackTenantId  上位で判明している場合の最終フォールバック値
     */
    public function resolveTenantId(string|int|null $fallbackTenantId = null): string|int|null
    {
        return $this->tenantId ?? tenant('id') ?? $fallbackTenantId;
    }

    /**
     * テナントコンテキストを初期化する。
     *
     * 1. tenantId が未設定の場合、ルートパラメータから取得を試みる。
     * 2. tenantId が存在し tenancy が未初期化の場合、該当テナントを初期化する。
     *
     * @param  Tenancy  $tenancy  stancl/tenancy インスタンス
     */
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
            }
        }

        // 2. tenantIdが取得できていて、かつテナンシーが初期化されていない場合に初期化する
        if ($this->tenantId && ! $tenancy->initialized) {
            $tenant = Tenant::find($this->tenantId); // find() を使う方が簡潔
            if ($tenant) {
                $tenancy->initialize($tenant);
                Log::info('Tenant re-initialized via InitializesTenantContext trait', ['tenant_id' => $this->tenantId]);
            } else {
                Log::error(
                    'Tenant not found for ID from property in InitializesTenantContext trait',
                    ['tenant_id' => $this->tenantId]
                );
            }
        }
    }

    /**
     * Livewire の boot フックからテナントコンテキストを自動初期化する。
     *
     * @param  Tenancy  $tenancy  stancl/tenancy インスタンス
     */
    public function bootInitializesTenantContext(Tenancy $tenancy): void
    {
        $this->initializeTenantContext($tenancy);
    }
}
