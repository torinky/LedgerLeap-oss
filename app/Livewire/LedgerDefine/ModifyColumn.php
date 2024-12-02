<?php

namespace App\Livewire\LedgerDefine;

use App\Models\ColumnDefine;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use RuntimeException;

class ModifyColumn extends Component
{
    use WithFileUploads;
    //    https://laravel-livewire.com/screencasts/s8-dragging-list
    public $ledgerDefineRecord;

    public $columnType = [];

    public $maxColumnId = 0;

    public $maxColumnOrder = 0;

    public $columnOptions = [];

    public $columnName = [];

    public $columnRequired = [];           // 必須項目フラグ

    public $columnUnique = [];     // 重複不可フラグ

    public $columnSortBy = [];             // ソート対象フラグ

    public $columnHint = []; // ヒント

    public $columnFile = []; // ファイル

    public $columnUploadedFile = [];

    public function mount(request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->columnType = collect($this->ledgerDefineRecord->column_define)->pluck('type', 'id');
        // idを0から開始できるようにする
        $this->maxColumnId = collect($this->ledgerDefineRecord->column_define)->pluck('id')->max();
        if (count($this->ledgerDefineRecord->column_define) == 0) {
            $this->maxColumnId = -1;
        }
        $this->maxColumnOrder = collect($this->ledgerDefineRecord->column_define)->pluck('order')->max();

        //        options初期化
        $this->columnOptions = collect($this->ledgerDefineRecord->column_define)->pluck('options', 'id');
        $this->columnName = collect($this->ledgerDefineRecord->column_define)->pluck('name', 'id');
//        dd($this->columnName);
        $this->columnRequired = collect($this->ledgerDefineRecord->column_define)->pluck('required', 'id');
        $this->columnUnique = collect($this->ledgerDefineRecord->column_define)->pluck('unique', 'id');
        $this->columnSortBy = collect($this->ledgerDefineRecord->column_define)->pluck('sortBy', 'id');
        $this->columnHint = collect($this->ledgerDefineRecord->column_define)->pluck('hint', 'id');
        $this->columnFile = collect($this->ledgerDefineRecord->column_define)->pluck('file', 'id')
            ->map(function ($value) {
                return (array)$value;
            });
        // サムネイル生成処理を追加
//        dd($this->columnFile);
        foreach ($this->columnFile as $columnId => $file) {
            if (!isset($file['path'])) {
                continue;
            }
            $this->createThumbnail($file['path']);
        }

        foreach ($this->columnFile as $columnId => $file) {
            $this->columnUploadedFile[$columnId] = (object)['name' => "", 'path' => ""];
        }
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
        $this->applyProperty($columnId, 'name', $this->columnName[$columnId] ?? []);
    }

    public function applyRequired($columnId)
    {
        $this->applyProperty($columnId, 'required', $this->columnRequired[$columnId] ?? []);
    }

    public function applyType($columnId)
    {
        $this->applyProperty($columnId, 'type', $this->columnType[$columnId] ?? []);
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

    public function updated($propertyName)
    {

        $this->validateOnly($propertyName);

        $propertyParts = explode('.', $propertyName);
        $columnId = (int)end($propertyParts);
        $classPropertyName = reset($propertyParts);
        $columnDefinePropertyName = Str::camel(strtr($classPropertyName, ['column' => '']));

//        dd($classPropertyName, $columnDefinePropertyName, $columnId);
        if ($classPropertyName === 'columnUploadedFile') {
            $this->storeFile($columnId);
        } else {
            $this->applyProperty($columnId, $columnDefinePropertyName, $this->{$classPropertyName}[$columnId]);
        }


    }

    /**
     * @param $path
     * @return void
     */
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

    /**
     * このクラスのpublicパラメータとcolumnDefineクラスに合わせてバリデーションの内容を定義します。
     */
    protected function rules(): array
    {
        $validationRules = [
            'columnName.*' => 'required|string|max:255',
//            'columnType.*' => 'required|string|in:text,number,date',
            'columnType.*' => 'required|string',
            'columnOptions.*' => 'array',
            'columnRequired.*' => 'boolean',
            'columnUnique.*' => 'boolean',
            'columnSortBy.*' => 'boolean',
            'columnHint.*' => 'nullable|string|max:255',
//            'columnFile.*' => 'nullable|file|mimes:png,jpg,pdf|max:10240',
//            'columnUploadedFile.*' => 'nullable',
            'columnUploadedFile.*' => 'nullable|file|mimes:png,jpg,pdf|max:10240',
        ];
        // Add dynamic rules based on columnDefine
        foreach ($this->ledgerDefineRecord->column_define as $columnDefine) {
            // Example dynamic rule, customize as needed
            $validationRules["columnOptions.{$columnDefine->id}"] = 'required_if:columnTypes.' . $columnDefine->id . ',select';
        }

        return $validationRules;
    }

    public function storeFile($columnId)
    {
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
            $this->store();
        }
    }

    public function deleteFile($columnId)
    {
        $filePath = $this->ledgerDefineRecord->column_define[$columnId]->file->path ?? null;

        if ($filePath && Storage::disk('public')->exists($filePath)) {
            Storage::disk('public')->delete($filePath);
        }

        $this->columnFile[$columnId] = null;
        $this->ledgerDefineRecord->column_define[$columnId]->file = null;
        $this->store();
    }
}
