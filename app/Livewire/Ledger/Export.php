<?php

namespace App\Livewire\Ledger;

use App\Jobs\Ledger\ExportJob;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\LedgerDefine;
use App\Services\Ledger\ExportCacheService;
use Illuminate\Support\Facades\Bus;
use Mary\Traits\Toast;

/**
 * Ledgerのエクスポートを管理するLivewireコンポーネント
 */
class Export extends BaseLivewireComponent
{
    use InitializesTenantContext;
    use Toast;

    public $batchId;

    public $exporting = false;

    public $exportFinished = false;

    public $exportFilename = 'transactions.csv';

    public $keywords = [];

    public $filter = [];

    public ?int $ledgerDefineId = null;

    /**
     * コンポーネントをマウントするメソッド
     *
     * @param  int  $ledgerDefineId  Ledger定義のID
     * @param  string  $keywords  キーワード情報（JSON形式）
     * @param  string  $filter  フィルター情報（JSON形式）
     * @param  string|null  $ledgerDefineTitle  Ledger定義のタイトル（省略時はDB取得）
     */
    public function mount($ledgerDefineId, $keywords, $filter, $ledgerDefineTitle = null)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->keywords = is_array($keywords) ? $keywords : json_decode($keywords ?? '[]', true);
        $this->filter = is_array($filter) ? $filter : json_decode($filter ?? '[]', true);

        // Livewire の /livewire/update リクエストでは route に {tenant} がないため、
        // 初回 mount 時に tenantId を明示的に保存しておく（.github/instructions/livewire.instructions.md Pattern B）
        if (is_null($this->tenantId)) {
            $this->tenantId = request()->route()?->originalParameters()['tenant'] ?? null;
        }

        $cacheService = app(ExportCacheService::class);
        $this->exportFilename = $cacheService->buildFilename(
            $this->ledgerDefineId,
            $this->keywords,
            $this->filter
        );

        // 既に同じ条件のCSVが存在すれば、初回レンダリング時からダウンロードボタンを表示
        if ($cacheService->exists($this->exportFilename)) {
            $this->exportFinished = true;
        }
    }

    /**
     * エクスポートを開始する
     *
     * Alpine.js から現在の keywords / filter を引数として受け取る。
     * 渡された値が空の場合は mount 時に保存した値にフォールバックする。
     *
     * @param  array  $keywords  検索キーワード（Alpine.js 側の最新値）
     * @param  array  $filter  フィルター条件（Alpine.js 側の最新値）
     */
    public function export(array $keywords = [], array $filter = [])
    {
        // Alpine.js から渡された最新の検索条件を優先し、なければ mount 時の値を使用する
        if (! empty($keywords)) {
            $this->keywords = $keywords;
        }
        if (! empty($filter)) {
            $this->filter = $filter;
        }

        $cacheService = app(ExportCacheService::class);
        $this->exportFilename = $cacheService->buildFilename(
            $this->ledgerDefineId,
            $this->keywords,
            $this->filter
        );

        // 既に同じ条件のCSVが存在すれば、バッチ生成をスキップして即ダウンロード可能にする
        if ($cacheService->exists($this->exportFilename)) {
            $this->exporting = false;
            $this->exportFinished = true;
            $this->success(__('ledger.export_ready'));

            return;
        }

        $columnDefines = LedgerDefine::findOrFail($this->ledgerDefineId)->column_define
            ->sortBy('order')
            ->values();

        $this->exporting = true;
        $this->exportFinished = false;

        $batch = Bus::batch([
            new ExportJob($this->ledgerDefineId, $this->keywords, $this->filter, $columnDefines, $this->exportFilename),
        ])->dispatch();

        $this->batchId = $batch->id;
    }

    public function getExportBatchProperty()
    {
        if (! $this->batchId) {
            return null;
        }

        return Bus::findBatch($this->batchId);
    }

    public function getDownloadUrlProperty(): string
    {
        // /livewire/update では tenant() が null のため、$this->tenantId を優先し、
        // 万が一 null の場合は LedgerDefine からフォールバックする（Pattern A）
        $tenantId = $this->resolveTenantId();
        if (is_null($tenantId)) {
            $tenantId = LedgerDefine::find($this->ledgerDefineId)?->tenant_id;
        }

        return route('ledger.export.download', [
            'tenant' => $tenantId,
            'ledgerDefineId' => $this->ledgerDefineId,
            'filename' => $this->exportFilename,
        ]);
    }

    public function updateExportProgress()
    {
        if ($this->exportFinished) {
            return;
        }

        $this->exportFinished = $this->exportBatch->finished();

        if ($this->exportFinished) {
            $this->exporting = false;
            $this->success(__('ledger.export_ready'));
        }
    }

    public function render()
    {
        return view('livewire.ledger.export');
    }
}
