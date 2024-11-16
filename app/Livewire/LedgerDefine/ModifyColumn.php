<?php

namespace App\Livewire\LedgerDefine;

use App\Models\ColumnDefine;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Livewire\Component;

class ModifyColumn extends Component
{
    //    https://laravel-livewire.com/screencasts/s8-dragging-list
    public $ledgerDefineRecord;

    public $columnTypes = [];

    public $maxColumnId = 0;

    public $maxColumnOrder = 0;

    public function mount(request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->columnTypes = collect($this->ledgerDefineRecord->column_define)->pluck('type', 'id')->toArray();
        // idを0から開始できるようにする
        $this->maxColumnId = collect($this->ledgerDefineRecord->column_define)->pluck('id')->max() - 1;
        $this->maxColumnOrder = collect($this->ledgerDefineRecord->column_define)->pluck('order')->max();

    }

    public function render(request $request)
    {
        return view('livewire.ledger-define.modify-column', [
            'columnInputTypes' => ColumnDefine::typeLabels(),
        ]);
    }

    /**
     * @param array $columnOrder
     * @return void
     */
    public function updateColumnOrder($columnOrder)
    {
        // https://laravel-livewire.com/screencasts/s8-dragging-list
        $this->ledgerDefineRecord->column_define = collect($columnOrder)->map(function ($order, $key) {
            $result = collect($this->ledgerDefineRecord->column_define)->where('id', (int)$order['value'])->firstOrFail();
            $result->order = $order['order'];

            return $result;
        })->toArray();

        $this->ledgerDefineRecord->save();

    }

    /**
     * @param int $columnId
     * @return void
     */
    public function removeColumn($columnId)
    {
        $this->ledgerDefineRecord->column_define = collect($this->ledgerDefineRecord->column_define)
//            ->dd()
            ->reject(fn($columnDefine, $key) => $columnDefine->id == $columnId)
//            ->dd()
            ->values()
            ->map(function ($columnDefine, $key) {
                $columnDefine->order = $key + 1;

                return $columnDefine;
            })
//            ->dd()
//            ->all();
            ->toArray();

        //      DBに投入しないと反映されない（レスポンスがhtmlなので変数がjsに再バインドされるわけでは無さそう）
        $this->ledgerDefineRecord->save();
    }

    public function addColumn()
    {

        $this->maxColumnId++;
        $this->maxColumnOrder++;

        $this->ledgerDefineRecord->column_define = collect($this->ledgerDefineRecord->column_define)
            ->add(
                new ColumnDefine($this->maxColumnId, 'no name', 'text', $this->maxColumnOrder)
            )
            ->toArray();
        $this->ledgerDefineRecord->save();

    }

    public function applyType()
    {

        foreach ($this->columnTypes as $columnId => $columnType) {
            foreach ($this->ledgerDefineRecord->column_define as $cKey => $columnDefine) {
                if ($columnDefine->id == $columnId) {
                    $this->ledgerDefineRecord->column_define[$cKey]->setType($columnType);
                }
            }
        }
        $this->dispatch('elementUpdated');

        //        配列に変換しないとキーが文字列型になりJSONオブジェクトになってしまう
        $this->ledgerDefineRecord->column_define = (array)$this->ledgerDefineRecord->column_define;
        $this->ledgerDefineRecord->save();

    }
}
