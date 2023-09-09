<?php

namespace App\Http\Livewire\Ledger;

use App\Imports\LedgerImport;
use App\Models\LedgerDefine;
use Illuminate\Cache\;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;

class Import extends Component
{
    use WithFileUploads;

    public $ledgerDefine;
    public $totalRows = 0;
    public $importFile;
    public $batchId;
    public $importing = false;
    public $importFilePath;
    public $importFinished = false;
    /**
     * @var |mixed
     */
    public $currentRows = 0;
    protected $listeners = ['refreshComponent' => '$refresh'];

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
//        $this->importFinished = $this->importBatch->finished();
        $this->importFinished = Cache::has("end_date_" . $this->ledgerDefine->id) && Cache::get("end_date_" . $this->ledgerDefine->id) < now();

        if ($this->importFinished) {
//            Storage::delete($this->importFilePath);
            $this->importing = false;
        }

//        return cache('total_rows_'.$this->ledgerDefine->id);
    }


    /**
     * CSVファイルからLedgerの内容をインポートする
     *
     */
    public function importExcelCSV()
    {
        $this->importing = true;
        $this->importFinished = false;
        $this->emit('refreshComponent');
//        return;
        // ファイルアップロードのバリデーションなどを行う必要があるかもしれません

//        $file = $request->file('csv_file');


        /*        $ledgerDefine = LedgerDefine::where('id', $request->input('ledger_define_id'))
                    ->first();*/
        $import = new LedgerImport($this->ledgerDefine);


//        $columnDefines= $ledgerDefine->column_define;

        // カラム定義を取得して、適切な変換ロジックを適用
        /*        foreach ($columnDefines as $columnDefine) {
                    $import->applyColumnMapping($columnDefine->id, $columnDefine->restoreColumnValue($columnDefine->id));
                }*/

        // maatwebsite/excel パッケージを使用してインポートを実行
        Excel::import($import, $this->importFile, null, \Maatwebsite\Excel\Excel::CSV);
        $this->importFile = null;

    }

}
