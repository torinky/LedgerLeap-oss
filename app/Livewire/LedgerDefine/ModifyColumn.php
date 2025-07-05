<?php

namespace App\Livewire\LedgerDefine;

use App\Enums\WorkflowStatus;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Mary\Traits\Toast;
use RuntimeException;

class ModifyColumn extends Component
{
    use Toast, WithFileUploads;

    #[Locked]
    public LedgerDefine $ledgerDefineRecord;

    public array $columns = [];

    public $columnUploadedFile = [];

    public function mount(Request $request): void
    {
        if ($request->isMethod('POST')) {
            return;
        }

        $ledgerDefineId = (int)$request->route('ledgerDefineId');
        $this->ledgerDefineRecord = LedgerDefine::findOrNew($ledgerDefineId);

        // Ensure $this->columns is initialized as an array of associative arrays.
        $this->columns = collect($this->ledgerDefineRecord->column_define)->map(function ($columnDefineObject) {
            // ColumnDefineオブジェクトのプロパティを連想配列に変換
            return [
                'id' => $columnDefineObject->id,
                'name' => $columnDefineObject->name,
                'type' => $columnDefineObject->type,
                'order' => $columnDefineObject->order,
                'useOptions' => $columnDefineObject->useOptions,
                'options' => (array) $columnDefineObject->options,
                'required' => (bool) $columnDefineObject->required,
                'unique' => (bool) $columnDefineObject->unique,
                'sortBy' => (bool) $columnDefineObject->sortBy,
                'hint' => (string) $columnDefineObject->hint,
                'file' => (array) $columnDefineObject->file,
            ];
        })->values()->all(); // values()でキーをリセットし、インデックス付き配列にする

        // サムネイル生成とアップロード済みファイル用の初期化
        foreach ($this->columns as $index => $column) {
            if (isset($column['file']['path'])) {
                $this->createThumbnail($column['file']['path']);
            }
            // アップロード用プロパティの初期化
            $this->columnUploadedFile[$column['id']] = null;
        }
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
    public function updateColumnOrder($orderedItems)
    {
        // $orderedItems is an array of arrays, e.g., [['value' => 1, 'order' => 0], ['value' => 2, 'order' => 1]]
        // First, create a map of current columns by their IDs for efficient lookup
        $columnsById = collect($this->columns)->keyBy('id');

        $newOrderedColumns = [];
        foreach ($orderedItems as $item) {
            $id = (int) $item['value'];
            $order = (int) $item['order'];

            if ($columnsById->has($id)) {
                $column = $columnsById->get($id);
                $column['order'] = $order + 1; // Update the order based on the new position
                $newOrderedColumns[] = $column;
            }
        }

        // Sort the newOrderedColumns by their updated 'order' property
        usort($newOrderedColumns, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        $this->columns = $newOrderedColumns;
    }

    /**
     * @param int $columnId
     * @return void
     */
    public function removeColumn($index)
    {
        if (!$this->canModifyColumns()) return;

        $columns = collect($this->columns);
        $columns->forget($index);

        // Re-index the array and update order
        $this->columns = $columns->values()->map(function ($column, $key) {
            $column['order'] = $key + 1;
            return $column;
        })->all();
    }

    public function updatedColumnsType($value, $key)
    {
        // $key will be in the format "0.type", "1.type", etc.
        // We need to extract the numeric index.
        $parts = explode('.', $key);
        $columnIndex = (int) $parts[0];

        // Ensure the column exists
        if (isset($this->columns[$columnIndex])) {
            // Determine if the new type has options
            $hasOptions = \App\Models\ColumnTypes\InputTypeFactory::make($value)->hasOptions();

            // Update the useOptions property for the specific column
            $this->columns[$columnIndex]['useOptions'] = $hasOptions;

            // If the new type does not use options, clear any existing options
            if (!$hasOptions) {
                $this->columns[$columnIndex]['options'] = [];
            }
        }
    }

    public function saveColumn($index)
    {
        if (!$this->canModifyColumns()) return;

        $this->validate([
            "columns.{$index}.name" => 'required|string|max:255',
            "columns.{$index}.type" => 'required|string',
            "columns.{$index}.options" => 'array',
            "columns.{$index}.required" => 'boolean',
            "columns.{$index}.unique" => 'boolean',
            "columns.{$index}.sortBy" => 'boolean',
            "columns.{$index}.hint" => 'nullable|string|max:255',
        ]);

        // Update the main ledgerDefineRecord's column_define with the current state of $this->columns
        $this->ledgerDefineRecord->column_define = collect($this->columns)->map(function ($column) {
            return $column;
        })->toArray();

        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();

        $this->success(__('ledger.column.saved'));
    }

    public function addColumn()
    {
        if (!$this->canModifyColumns()) return;

        $maxId = collect($this->columns)->max('id') ?? -1;

        $newColumn = [
            'id' => $maxId + 1,
            'name' => 'no name',
            'type' => 'text',
            'order' => count($this->columns) + 1,
            'useOptions' => false, // Default value
            'options' => [], // Default value
            'required' => false, // Default value
            'unique' => false, // Default value
            'sortBy' => false, // Default value
            'hint' => '', // Default value
            'file' => [], // Default value
        ];

        $this->columns[] = $newColumn;
    }

    public function save()
    {
        if (!$this->canModifyColumns()) return;

        // Before saving, ensure all columns are simple associative arrays.
        $this->ledgerDefineRecord->column_define = collect($this->columns)->map(function ($column) {
            // Just return the array, as it should already be in the correct format.
            return $column;
        })->toArray();

        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();

        $this->success(__('ledger.define.saved'));
    }

    protected function rules(): array
    {
        return [
            'columns.*.name' => 'required|string|max:255',
            'columns.*.type' => 'required|string',
            'columns.*.options' => 'array',
            'columns.*.required' => 'boolean',
            'columns.*.unique' => 'boolean',
            'columns.*.sortBy' => 'boolean',
            'columns.*.hint' => 'nullable|string|max:255',
            'columnUploadedFile.*' => 'nullable|file|mimes:png,jpg,pdf|max:10240',
        ];
    }

    public function storeFile($columnId)
    {
        if (!$this->canModifyColumns()) return;

        if (isset($this->columnUploadedFile[$columnId])) {
            //            dd($this->columnUploadedFile[$columnId]);
            $file = $this->columnUploadedFile[$columnId];
            //            dd($this->columnFile[$columnId]);
            $originalFileName = $file->getClientOriginalName();
            $fileName = "ledger_{$this->ledgerDefineRecord->id}_column_{$columnId}_{$originalFileName}";
            $filePath = $file->storeAs('column_files', $fileName, 'public');
            $this->createThumbnail($filePath);
            $this->columnFile[$columnId] = ['name' => $originalFileName, 'path' => $filePath];
            $this->ledgerDefineRecord->column_define[$columnId]->file = $this->columnFile[$columnId];

            $this->backgroundImages[$columnId] = asset('storage/' . $filePath);
            $this->dispatch('applyBackgroundImages', $this->backgroundImages);

            $this->store();

        }
    }

    public function deleteFile($columnId)
    {
        if (!$this->canModifyColumns()) return;

        $filePath = $this->ledgerDefineRecord->column_define[$columnId]->file->path ?? null;

        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        $this->columnFile[$columnId] = null;
        $this->ledgerDefineRecord->column_define[$columnId]->file = null;

        $this->backgroundImages[$columnId] = null;
        $this->dispatch('applyBackgroundImages', $this->backgroundImages);

        $this->store();
    }

    /**
     * 台帳定義の列構成を変更可能かチェックする
     * @return bool true: 変更可能, false: 変更不可
     */
    private function canModifyColumns(): bool // メソッド名変更
    {
        $pendingCount = Ledger::where('ledger_define_id', $this->ledgerDefineRecord->id)
            ->whereIn('status', [
                WorkflowStatus::PENDING_INSPECTION,
                WorkflowStatus::PENDING_APPROVAL
            ])->count();

        if ($pendingCount > 0) {
            $this->error(__('ledger.define.cannot_modify_while_workflow'));
            return false;
        }
        return true;
    }

}
