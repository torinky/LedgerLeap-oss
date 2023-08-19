<?php

namespace App\Http\Livewire\Ledger;

use App\Http\Requests\Ledger\StoreRequest;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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

    public function store(StoreRequest $request)
    {
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


}
