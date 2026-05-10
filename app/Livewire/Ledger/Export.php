<?php

namespace App\Livewire\Ledger;

use App\Jobs\Ledger\ExportJob;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\LedgerDefine;
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
        $this->keywords = $keywords;
        $this->filter = $filter;

        if ($ledgerDefineTitle) {
            $this->exportFilename = $ledgerDefineTitle.'.csv';
        } else {
            $this->exportFilename = LedgerDefine::find($this->ledgerDefineId)->title.'.csv';
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
        return route('ledger.export.download', [
            'tenant' => $this->resolveTenantId(),
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
