<?php

namespace App\Livewire\Ledger;

use App\Exports\LedgerExport;
use App\Jobs\Ledger\ExportJob;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Ledgerのエクスポートを管理するLivewireコンポーネント
 */
class Export extends Component
{
    public $batchId;
    public $exporting = false;
    public $exportFinished = false;
    public $exportFilename = 'transactions.csv';
    public $keywords = [];
    public $filter = [];
    public ?int $ledgerDefineId = null;
//    protected $listeners = ['refreshChildren' => 'updateFromParent'];
    private ?LedgerExport $ledgerExport = null;

    /**
     * 親からの更新を受け取るメソッド
     *
     * @param array $data キーワードとフィルター情報を含む配列
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
     * @param int $ledgerDefineId Ledger定義のID
     * @param string $keywords キーワード情報（JSON形式）
     * @param string $filter フィルター情報（JSON形式）
     */
    public function mount($ledgerDefineId, $keywords, $filter)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->keywords = json_decode($keywords, true);
        $this->filter = json_decode($filter, true);
        $this->exportFilename = LedgerDefine::find($this->ledgerDefineId)->title . '.csv';
    }

    public function export()
    {
        $columnDefines = LedgerDefine::where('id', $this->ledgerDefineId)->pluck('column_define')->sortBy('order')->all()[0];

        $this->exporting = true;
        $this->exportFinished = false;

        $batch = Bus::batch([
            new ExportJob($this->ledgerDefineId, $this->keywords, $this->filter, $columnDefines, $this->exportFilename),
        ])->dispatch();

        $this->batchId = $batch->id;
    }

    public function getExportBatchProperty()
    {
        if (!$this->batchId) {
            return null;
        }

        return Bus::findBatch($this->batchId);
    }

    public function downloadExport()
    {
        $headers = ['Content-Disposition' => 'attachment; filename="' . $this->exportFilename . '"'];
        return Storage::download('public/' . $this->exportFilename, $this->exportFilename, $headers);
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
