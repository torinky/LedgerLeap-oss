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

    public $columnOptions = [];

    public $columnNames = [];

    public $columnRequired = [];           // 必須項目フラグ

    public $columnUnique = [];     // 重複不可フラグ

    public $columnSortBy = [];             // ソート対象フラグ

    public function mount(request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->columnTypes = collect($this->ledgerDefineRecord->column_define)->pluck('type', 'id')->toArray();
        // idを0から開始できるようにする
        $this->maxColumnId = collect($this->ledgerDefineRecord->column_define)->pluck('id')->max();
        if (count($this->ledgerDefineRecord->column_define) == 0) {
            $this->maxColumnId = -1;
        }
        $this->maxColumnOrder = collect($this->ledgerDefineRecord->column_define)->pluck('order')->max();

        //        options初期化
        $this->columnOptions = collect($this->ledgerDefineRecord->column_define)->pluck('options', 'id')->toArray();
        $this->columnNames = collect($this->ledgerDefineRecord->column_define)->pluck('name', 'id')->toArray();
        $this->columnRequired = collect($this->ledgerDefineRecord->column_define)->pluck('required', 'id')->toArray();
        $this->columnUnique = collect($this->ledgerDefineRecord->column_define)->pluck('unique', 'id')->toArray();
        $this->columnSortBy = collect($this->ledgerDefineRecord->column_define)->pluck('sortBy', 'id')->toArray();
        //        ksort($this->columnOptions);

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

        $this->store();

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
        $this->store();
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
        $this->store();

    }

    /*    public function applyType()
        {

            foreach ($this->columnTypes as $columnId => $columnType) {
                foreach ($this->ledgerDefineRecord->column_define as $cKey => $columnDefine) {
                    if ($columnDefine->id == $columnId) {
                        $this->ledgerDefineRecord->column_define[$cKey]->setType($columnType);
                    }
                }
            }

            //        配列に変換しないとキーが文字列型になりJSONオブジェクトになってしまう
            $this->ledgerDefineRecord->column_define = (array) $this->ledgerDefineRecord->column_define;
            $this->store();

        }*/

    public function applyOptions($columnId)
    {

        foreach ($this->ledgerDefineRecord->column_define as $cKey => $columnDefine) {
            if ($columnDefine->id == $columnId) {
                $oldOptions = $this->ledgerDefineRecord->column_define[$cKey]->options;
                break;
            }
        }

        $newOptions = $this->columnOptions[$columnId] ?? [];

        // 比較ロジック
        if ($this->optionsHaveChanged($oldOptions, $newOptions)) {
            $this->ledgerDefineRecord->column_define[$cKey]->options = $newOptions;
            //            $this->ledgerDefineRecord->column_define = (array) $this->ledgerDefineRecord->column_define;
            //            dd($this->ledgerDefineRecord->column_define);
            $this->store();
        }
    }

    private function optionsHaveChanged($oldOptions, $newOptions)
    {
        return array_diff($oldOptions, $newOptions) !== [] || array_diff($newOptions, $oldOptions) !== [];
    }

    public function applyProperty($columnId, $propertyName, $newValue)
    {
        $setterMethod = 'set' . ucfirst($propertyName);
        foreach ($this->ledgerDefineRecord->column_define as $cKey => $columnDefine) {
            if ($columnDefine->id == $columnId) {
                $oldValue = $columnDefine->$propertyName;
                break;
            }
        }

        if ($oldValue != $newValue) {
            foreach ($this->ledgerDefineRecord->column_define as $cKey => $columnDefine) {
                if ($columnDefine->id == $columnId) {
                    $columnDefine->$setterMethod($newValue);
                    break;
                }
            }
            $this->store();
        }
    }

    public function applyName($columnId)
    {
        $this->applyProperty($columnId, 'name', $this->columnNames[$columnId] ?? []);
    }

    public function applyRequired($columnId)
    {
        $this->applyProperty($columnId, 'required', $this->columnRequired[$columnId] ?? []);
    }

    public function applyType($columnId)
    {
        $this->applyProperty($columnId, 'type', $this->columnTypes[$columnId] ?? []);
    }

    public function applyUnique($columnId)
    {
        $this->applyProperty($columnId, 'unique', $this->columnUnique[$columnId] ?? []);
    }

    public function applySortBy($columnId)
    {
        $this->applyProperty($columnId, 'sortBy', $this->columnSortBy[$columnId] ?? []);
    }

    public function store()
    {
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();
        // イベントを発行
        $this->dispatch('ledgerDefineRecordStored');

        // セッションにメッセージを保存
        session()->flash('status', __('ledger.define.saved'));

    }
}
