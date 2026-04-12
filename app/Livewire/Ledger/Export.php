<?php

namespace App\Livewire\Ledger;

use App\Jobs\Ledger\ExportJob;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;

/**
 * Ledgerのエクスポートを管理するLivewireコンポーネント
 */
class Export extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public $batchId;

    public $exporting = false;

    public $exportFinished = false;

    public $exportFilename = 'transactions.csv';

    public $keywords = [];

    public $filter = [];

    public ?int $ledgerDefineId = null;

    //    protected $listeners = ['refreshChildren' => 'updateFromParent'];

    /**
     * 親からの更新を受け取るメソッド
     *
     * @param  array  $data  キーワードとフィルター情報を含む配列
     */
    #[On('refreshChildren')]
    public function updateFromParent($data)
    {
        $this->keywords = $data['keywords'];
        $this->filter = $data['filter'];
    }

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
        //        $this->keywords = json_decode($keywords, true);
        $this->keywords = $keywords;
        //        $this->filter = json_decode($filter, true);
        $this->filter = $filter;

        // 親から渡された場合はそれを使用、なければDB取得
        if ($ledgerDefineTitle) {
            $this->exportFilename = $ledgerDefineTitle.'.csv';
        } else {
            $this->exportFilename = LedgerDefine::find($this->ledgerDefineId)->title.'.csv';
        }
    }

    public function export()
    {
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

    public function downloadExport()
    {
        $headers = ['Content-Disposition' => 'attachment; filename="'.$this->exportFilename.'"'];

        return Storage::disk('public')->download($this->exportFilename, $this->exportFilename, $headers);
    }

    public function updateExportProgress()
    {
        $this->exportFinished = $this->exportBatch->finished();

        if ($this->exportFinished) {
            $this->exporting = false;
        }
    }

    public function render()
    {
        return view('livewire.ledger.export');
    }
}
