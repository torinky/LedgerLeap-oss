<?php

namespace App\Livewire\Ledger;

use App\Imports\LedgerImport;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use App\Livewire\Traits\InitializesTenantContext;

class Import extends Component
{
    use WithFileUploads, InitializesTenantContext;

    public $ledgerDefine;

    public $totalRows = 0;

    public $importFile;

    public $batchId;

    public $importing = false;

    public $importFinished = false;

    public $importMode = LedgerImport::MODE_UPDATE;

    /**
     * @var |mixed
     */
    public $currentRows = 0;

    protected $listeners = ['refreshComponent' => '$refresh'];

    /**
     * @var |mixed
     */
    public mixed $insertRows;

    /**
     * @var |mixed
     */
    public mixed $updateRows;

    public function mount(Request $request)
    {
        $this->ledgerDefine = LedgerDefine::where('id', $request->route('ledgerDefineId'))
            ->first();
        //dd($request);
    }

    public function render()
    {
        return view('livewire.ledger.import');
    }

    public function updateImportProgress()
    {
        $this->totalRows = Cache::get('total_rows_' . $this->ledgerDefine->id);
        $this->currentRows = Cache::get('current_rows_' . $this->ledgerDefine->id);
        $this->insertRows = Cache::get('insert_rows_' . $this->ledgerDefine->id);
        $this->updateRows = Cache::get('update_rows_' . $this->ledgerDefine->id);
        //        $this->importFinished = $this->importBatch->finished();
        $this->importFinished = Cache::has('end_date_' . $this->ledgerDefine->id) && Cache::get('end_date_' . $this->ledgerDefine->id) < now();

        if ($this->importFinished) {
            //            Storage::delete($this->importFilePath);
            $this->importing = false;
        }

        //        return cache('total_rows_'.$this->ledgerDefine->id);
    }

    /**
     * CSVファイルからLedgerの内容をインポートする
     */
    public function importExcelCSV()
    {
        $this->importing = true;
        $this->importFinished = false;
        $this->dispatch('refreshComponent');
        // ファイルアップロードのバリデーションなどを行う必要があるかもしれません

        //        $file = $request->file('csv_file');

        $import = new LedgerImport($this->ledgerDefine, $this->importMode);

        // maatwebsite/excel パッケージを使用してインポートを実行
        Excel::import($import, $this->importFile, null, \Maatwebsite\Excel\Excel::CSV);
        $this->importFile = null;

    }
}
