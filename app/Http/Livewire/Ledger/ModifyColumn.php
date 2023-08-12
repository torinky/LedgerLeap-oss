<?php

namespace App\Http\Livewire\Ledger;

use App\Http\Requests\Ledger\StoreRequest;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ModifyColumn extends CreateColumn
{

    public $deletedContent = [];
    public function mount(request $request)
    {
        $this->ledgerId = (int)$request->route('ledgerId');
        if ($this->ledgerId) {
            //edit
            $this->ledgerRecord = Ledger::with('define')->where('ledgers.id', $this->ledgerId)->firstOrFail();
            $this->ledgerDefineId = $this->ledgerRecord->ledger_define_id;
            $this->ledgerDefineRecord = $this->ledgerRecord->define;
            $this->content = $this->ledgerRecord->content;

            foreach ($this->ledgerDefineRecord->column_define as $cKey => $column) {
                if ($column->type == 'files') {
                    $this->deletedContent[$column->id] = [];
                    $this->content[$column->id] = [];
                }
            }
        }
    }

    public function render(): View
    {
        return view('livewire.ledger.modify-column');
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function store(StoreRequest $request)
    {
//        $this->validate($this->getValidationRules());
        $this->validate();

        foreach ($this->ledgerDefineRecord->column_define as $cKey => $column) {
            if ($column->type == 'files') {
                $this->mergeContentFiles($column);
            }
        }

        if ($this->ledgerId) {
            $this->storeLedgerDiff();

            $ledgerRecord = Ledger::find($this->ledgerId);
            $ledgerRecord->content = $this->content;
            $ledgerRecord->modifier_id = Auth::user()->id;
            $ledgerRecord->save();
            return redirect()->route('ledger.show', ['ledgerId' => $ledgerRecord->id])
                ->with('status', __('ledger record updated successfully !'));
        }

    }

    /**
     * @param mixed $column
     * @return void
     */
    public function mergeContentFiles(mixed $column): void
    {
        //新規登録したファイルの保存
        $filenames = $this->storeFile($column->id);
        $this->content[$column->id] = $filenames;

        //既存ファイルの削除処理
        if (!empty($this->ledgerRecord->content[$column->id])) {
            /*
             * fileの保存状態
             * ['originalFilename'=>'savedFilePath']
             */
            $tmpContent = $this->ledgerRecord->content[$column->id];
            foreach ($this->ledgerRecord->content[$column->id] as $originalFilename => $filepath) {
                if (in_array($filepath, $this->deletedContent[$column->id], true)) {
                    unset($tmpContent[$originalFilename]);
                    //実体ファイルを消したければここに削除処理を追加
                }
            }
            //以前保存したファイルとのマージ
            $this->content[$column->id] = array_merge($filenames, $tmpContent);
        }
    }


    private function getThumbnailUrl($filename)
    {
        return Storage::url('Ledger/thumbs/' . basename($filename));
    }

    /**
     * @return void
     */
    public function storeLedgerDiff(): void
    {
        $ledgerDiff = new LedgerDiff();
        $ledgerDiff->timestamps = false;
        $ledgerDiff->create([
            'content' => $this->ledgerRecord->content,
            'column_define' => $this->ledgerDefineRecord->column_define,
            'ledger_id' => $this->ledgerRecord->id,
            'ledger_define_id' => $this->ledgerDefineRecord->id,
            'modifier_id' => $this->ledgerRecord->modifier_id,
            'creator_id' => $this->ledgerRecord->creator_id,
            'created_at' => $this->ledgerRecord->created_at,
            'updated_at' => $this->ledgerRecord->updated_at,
        ]);
    }

    /**
     * バリデーションルールを取得します。
     *
     * @return array
     */
    protected function rules(): array
    {
        $validationRules = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $columnId = $column->id;
            $columnName = 'content.' . $columnId;
            $columnType = $column->type;

            $rules = [];

            // カラムの種類に基づいた共通のバリデーションルールを追加
            if ($columnType === 'text' || $columnType === 'textarea') {
                $rules[] = 'string';
            } elseif ($columnType === 'number') {
                $rules[] = 'string';
            } elseif ($columnType === 'YMD') {
                $rules[] = 'date_format:Y-m-d';
            } elseif ($column->type === 'chk' && $column->useOptions && !empty($column->options)) {
                // チェックボックスのバリデーションルールを定義
                $rules["content.{$column->id}"] = ['in_options', $column->options];

                // 必須項目で少なくとも1つの選択肢をチェックするルールを追加
                if ($column->required) {
                    array_unshift($rules["content.{$column->id}"], 'at_least_one_checked');
                }

            } elseif ($columnType === 'select') {
                $rules[] = Rule::in($column->options);
            } elseif ($columnType === 'files') {
//                $rules[] = 'array';
            }

            // 必要に応じて追加のバリデーションルールを追加
            if ($column->required & $column->type !== 'chk') {
                $rules[] = 'required';
            }

            // カラムごとのバリデーションルールを配列に追加
            $validationRules[$columnName] = $rules;
        }

        return $validationRules;
    }

    protected function validationAttributes()
    {
        $attributes = [];

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            $attributes["content.{$column->id}"] = $column->name;
        }

        return $attributes;
    }

    protected function messages()
    {
        return [
            'content.*.in_options' => ':attribute が無効な選択肢です。',
            'content.*.at_least_one_checked' => ':attribute を少なくとも1つ選択してください。',

        ];

    }

}
