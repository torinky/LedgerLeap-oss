<?php

namespace App\Livewire\LedgerDefine;

use App\Enums\WorkflowStatus;
use App\Models\ColumnDefine;
use App\Models\ColumnTypes\AutoNumberType;
use App\Models\ColumnTypes\InputTypeFactory;
use App\Models\ColumnTypes\NumberType;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Mary\Traits\Toast;
use App\Livewire\Traits\InitializesTenantContext;

class ModifyColumn extends Component
{
    use Toast, WithFileUploads, InitializesTenantContext;

    #[Locked]
    public LedgerDefine $ledgerDefineRecord;

    public array $columns = [];

    public $columnUploadedFile = [];

    public bool $isDirty = false; // フォームが変更されたかどうかを追跡

    public array $groupNames = [];

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
            $column = [
                'id' => $columnDefineObject->id,
                'name' => $columnDefineObject->name,
                'type' => $columnDefineObject->type,
                'order' => $columnDefineObject->order,
                'useOptions' => $columnDefineObject->useOptions,
//                'options' => (array)$columnDefineObject->options,
                'required' => (bool)$columnDefineObject->required,
                'unique' => (bool)$columnDefineObject->unique,
                'sortBy' => (bool)$columnDefineObject->sortBy,
                'hint' => (string)$columnDefineObject->hint,
                'file' => (array)$columnDefineObject->file,
                'display_level' => $columnDefineObject->display_level,
                'group' => $columnDefineObject->group,
                'is_collapsed' => false, // 初期状態で折りたたむ
                'options' => array_merge(
                    (array)$columnDefineObject->options,
                    $columnDefineObject->getInputType() instanceof NumberType ? [
                        'min' => $columnDefineObject->getInputType()->min,
                        'max' => $columnDefineObject->getInputType()->max,
                        'step' => $columnDefineObject->getInputType()->step,
                        'unit' => $columnDefineObject->getInputType()->unit,
                    ] : [],
                    $columnDefineObject->getInputType() instanceof AutoNumberType ? [
                        'prefix' => $columnDefineObject->getInputType()->prefix,
                        'digits' => $columnDefineObject->getInputType()->digits,
                        'revision' => $columnDefineObject->getInputType()->revision,
                    ] : []
                ),
            ];
            return $column;
        })->values()->all(); // values()でキーをリセットし、インデックス付き配列にする

        $this->searchGroups();

        // サムネイル生成とアップロード済みファイル用の初期化
        foreach ($this->columns as $index => $column) {
            if (isset($column['file']['path'])) {
                $this->createThumbnail($column['file']['path']);
            }
            // アップロード用プロパティの初期化
            $this->columnUploadedFile[$column['id']] = null;
        }

        $this->isDirty = false; // 初期化時にダーティフラグをリセット
    }

    public function render(request $request)
    {
        return view('livewire.ledger-define.modify-column', [
            'columnInputTypes' => ColumnDefine::typeLabels(),
        ]);
    }

    public function searchGroups(string $value = ''): void
    {
//        Log::info('search method called', ['value' => $value]);

        $allGroups = LedgerDefine::all()
            ->flatMap(fn($ledgerDefine) => collect($ledgerDefine->column_define)->pluck('group'))
            ->filter() // null や空文字列を除外
            ->unique() // 重複を除外
            ->values() // キーをリセット
            ->map(fn($group) => ['id' => $group, 'name' => $group]) // MaryUI choices の形式に変換
            ->toArray();

//        Log::info('search: allGroups', ['allGroups' => $allGroups]);

        if (empty($value)) {
//            Log::info('search: returning allGroups (empty value)');
            $this->groupNames = $allGroups; // 検索クエリがない場合は全グループを返す
            return;
        }

//        Log::info('search: filteredGroups', ['filteredGroups' => $filteredGroups]);

        // ユーザーが入力した値が既存のグループにない場合、それを新しい選択肢として追加
        if (!collect($this->groupNames)->contains('name', $value)) {
            array_unshift($this->groupNames, ['id' => $value, 'name' => $value]); // 先頭に追加
//            Log::info('search: added new value', ['newValue' => $value]);
        }

//        Log::info('search: final return', ['finalGroups' => $filteredGroups]);
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
            $id = (int)$item['value'];
            $order = (int)$item['order'];

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

        $this->isDirty = true;
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

    public function updatedColumns($value, $key)
    {
        // $key will be in the format "0.type", "1.type", etc.
        // We need to extract the numeric index.
        $parts = explode('.', $key);
        $columnIndex = (int)$parts[0];
        $propertyName = $parts[1];

        if (isset($this->columns[$columnIndex]) && $propertyName === 'type') {
            // Determine if the new type has options
            $hasOptions = InputTypeFactory::make(['type' => $value])->hasOptions();

            // Update the useOptions property for the specific column
            $this->columns[$columnIndex]['useOptions'] = $hasOptions;

            // If the new type does not use options, clear any existing options
            if (!$hasOptions) {
                $this->columns[$columnIndex]['options'] = [];
            }
            $this->isDirty = true; // フォームが変更された
        } elseif (isset($this->columns[$columnIndex]) && $propertyName === 'is_collapsed') {
            // is_collapsed の変更をAlpine.jsに通知
            $this->dispatch('toggle-collapse', ['is_collapsed' => $value])->self();
        } else {
            $this->isDirty = true; // その他のカラムプロパティが変更された
        }
    }

    public function saveColumn($index)
    {
        if (!$this->canModifyColumns()) return;

        $column = $this->columns[$index];
        $rules = [
            "columns.{$index}.name" => 'required|string|max:255',
            "columns.{$index}.type" => 'required|string',
            "columns.{$index}.options" => 'array',
            "columns.{$index}.required" => 'boolean',
            "columns.{$index}.unique" => 'boolean',
            "columns.{$index}.sortBy" => 'boolean',
            "columns.{$index}.hint" => 'nullable|string|max:255',
        ];

        if ($column['type'] === 'number') {
            $rules["columns.{$index}.options.min"] = 'required|numeric';
            $rules["columns.{$index}.options.max"] = ['required', 'numeric', 'gt:columns.' . $index . '.options.min'];
            $rules["columns.{$index}.options.step"] = ['required', 'numeric', 'min:0.000001', function ($attribute, $value, $fail) use ($column, $index) {
                $min = $column['options']['min'] ?? null;
                $max = $column['options']['max'] ?? null;
                if (is_numeric($min) && is_numeric($max) && is_numeric($value)) {
                    if (($max - $min) < $value) {
                        $fail(__('validation.custom.step_too_large'));
                    }
                }
            }];
            $rules["columns.{$index}.options.unit"] = 'nullable|string|max:255';
        } elseif ($column['type'] === 'auto_number') {
            $rules["columns.{$index}.options.prefix"] = 'nullable|string|max:255';
            $rules["columns.{$index}.options.digits"] = 'required|integer|min:1';
            $rules["columns.{$index}.options.revision"] = 'nullable|string|max:255';
        }

        $this->validate($rules);

        // ファイルがアップロードされている場合、storeFileを呼び出す
        if (isset($this->columnUploadedFile[$this->columns[$index]['id']])) {
            $this->storeFile($this->columns[$index]['id']);
        }

        // group プロパティが配列の場合に文字列に変換
        $saveForColumns = $this->columns;
        foreach ($saveForColumns as $key => $saveColumn) {
            if (is_array($saveColumn['group'])) {
                $saveForColumns[$key]['group'] = $saveColumn['group']['name'] ?? null;
            }
        }
        // Update the main ledgerDefineRecord's column_define with the current state of $this->columns
        $this->ledgerDefineRecord->column_define = collect($saveForColumns)->toArray();


        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();

        $this->isDirty = false; // 保存後にダーティフラグをリセット

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
            'display_level' => 3, // 追加: デフォルトの表示レベル
            'group' => null,      // 追加: デフォルトのグループ名
            'is_collapsed' => true, // 新規追加時は開いた状態にする
        ];

        $this->columns[] = $newColumn;
    }

    public function save()
    {
        if (!$this->canModifyColumns()) return;

        // Before saving, ensure all columns are simple associative arrays.
        $this->ledgerDefineRecord->column_define = collect($this->columns)->map(function ($column) {
            // group プロパティが配列の場合に文字列に変換
            if (is_array($column['group'])) {
                $column['group'] = $column['group']['name'] ?? null;
            }
            // Just return the array, as it should already be in the correct format.
            return $column;
        })->toArray();

        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();

        $this->isDirty = false; // 保存後にダーティフラグをリセット

        $this->success(__('ledger.define.saved'));
    }

    protected function rules(): array
    {
        $rules = [
            'columns.*.name' => 'required|string|max:255',
            'columns.*.type' => 'required|string',
            'columns.*.options' => 'array',
            'columns.*.required' => 'boolean',
            'columns.*.unique' => 'boolean',
            'columns.*.sortBy' => 'boolean',
            'columns.*.hint' => 'nullable|string|max:255',
            'columnUploadedFile.*' => 'nullable|file|mimes:png,jpg,pdf|max:10240',
            'columns.*.display_level' => ['required', 'integer', 'min:1', 'max:3'],
            'columns.*.group' => ['nullable', 'string', 'max:255'],
        ];

        foreach ($this->columns as $index => $column) {
            if ($column['type'] === 'number') {
                $rules["columns.{$index}.options.min"] = 'required|numeric';
                $rules["columns.{$index}.options.max"] = ['required', 'numeric', 'gt:columns.' . $index . '.options.min'];
                $rules["columns.{$index}.options.step"] = ['required', 'numeric', 'min:0.000001', function ($attribute, $value, $fail) use ($column, $index) {
                    $min = $column['options']['min'] ?? null;
                    $max = $column['options']['max'] ?? null;
                    if (is_numeric($min) && is_numeric($max) && is_numeric($value)) {
                        if (($max - $min) < $value) {
                            $fail(__('validation.custom.step_too_large'));
                        }
                    }
                }];
                $rules["columns.{$index}.options.unit"] = 'nullable|string|max:255';
            } elseif ($column['type'] === 'auto_number') {
                $rules["columns.{$index}.options.prefix"] = 'nullable|string|max:255';
                $rules["columns.{$index}.options.digits"] = 'required|integer|min:1';
                $rules["columns.{$index}.options.revision"] = 'nullable|string|max:255';
            }
        }

        return $rules;
    }

    public function storeFile($columnId)
    {
        if (!$this->canModifyColumns()) return;

        // columnUploadedFile は Livewire の一時ファイル
        if (isset($this->columnUploadedFile[$columnId])) {
            $file = $this->columnUploadedFile[$columnId];
            $originalFileName = $file->getClientOriginalName();
            $fileName = "ledger_{$this->ledgerDefineRecord->id}_column_{$columnId}_{$originalFileName}";
            $filePath = $file->storeAs('column_files', $fileName, 'public');

            // サムネイル作成 (必要であれば)
            $this->createThumbnail($filePath);

            // $this->columns 配列内の該当カラムの 'file' プロパティを更新
            foreach ($this->columns as $index => &$column) {
                if ($column['id'] == $columnId) {
                    $column['file'] = ['name' => $originalFileName, 'path' => $filePath];
                    break;
                }
            }
            unset($column); // 参照を解除

            // isDirty フラグを立てる
            $this->isDirty = true;

            // ファイルアップロード後の保存は saveColumn または save メソッドで行う
        }
    }

    public function deleteFile($columnId)
    {
        if (!$this->canModifyColumns()) return;

        // $this->columns から該当カラムを見つける
        $columnIndex = null;
        foreach ($this->columns as $idx => $column) {
            if ($column['id'] == $columnId) {
                $columnIndex = $idx;
                break;
            }
        }

        if (is_null($columnIndex)) {
            return; // カラムが見つからない場合は何もしない
        }

        $filePath = $this->columns[$columnIndex]['file']['path'] ?? null;
        $thumbnailPath = null;

        if ($filePath) {
            // サムネイルのパスを生成
            $pathParts = pathinfo($filePath);
            $thumbnailPath = 'thumbnails/' . $pathParts['dirname'] . '/' . $pathParts['filename'] . '.' . $pathParts['extension'];
        }

        // 元のファイルを削除
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        // サムネイルを削除
        if ($thumbnailPath && Storage::disk('public')->exists($thumbnailPath)) {
            Storage::disk('public')->delete($thumbnailPath);
        }

        // $this->columns 配列内の該当カラムの 'file' プロパティをクリア
        $this->columns[$columnIndex]['file'] = [];

        // isDirty フラグを立てる
        $this->isDirty = true;

        // ファイル削除後の保存は saveColumn または save メソッドで行う
        $this->save();
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

    public function createThumbnail($path): void
    {
        $thumbnailPath = Storage::disk('public')->path('thumbnails/' . $path);
        if (!is_dir(dirname($thumbnailPath))) {
            if (!mkdir($concurrentDirectory = dirname($thumbnailPath), 0777, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        $sourcePath = Storage::disk('public')->path($path);
        if (!file_exists($thumbnailPath) && file_exists($sourcePath)) {
            $img = Image::make($sourcePath);
            $img->resize(200, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $img->save($thumbnailPath);
        }
    }
}
