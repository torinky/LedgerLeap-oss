<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\StoreRequest;
use App\Models\AttachedFile;
use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ModifyColumn extends CreateColumn
{
    public array $deletedContent = [];

    private array $contentAttached = [];

    public function mount(request $request): void
    {
        $this->ledgerId = (int)$request->route('ledgerId');
        if ($this->ledgerId) {
            //edit
            $this->ledgerRecord = Ledger::with('define')->where('ledgers.id', $this->ledgerId)->firstOrFail();
            $this->ledgerDefineId = $this->ledgerRecord->ledger_define_id;
            if (!empty($this->ledgerRecord->define)) {
                $this->ledgerDefineRecord = $this->ledgerRecord->define;
            }
            if (!empty($this->ledgerRecord->content)) {
                $this->content = $this->ledgerRecord->content;
            }

            foreach ($this->ledgerDefineRecord->column_define as $column) {
                if ($column->type === 'files') {
                    $this->deletedContent[$column->id] = [];
                    $this->content[$column->id] = [];
                }
                if (!empty($this->content[$column->id])) {
                    $this->labelColor[$column->id] = 'success';
                } elseif ($column->required) {
                    $this->labelColor[$column->id] = 'warning';
                } else {
                    $this->labelColor[$column->id] = 'muted';
                }
            }
        }
    }

    public function render(): View
    {
        return view('livewire.ledger.modify-column');
    }

    /**
     * @throws Exception
     */
    public function store(StoreRequest $request)
    {
        $this->validate();

        foreach ($this->ledgerDefineRecord->column_define as $column) {
            if ($column->type === 'files') {
                $storedFiles = [];
                foreach ($this->content[$column->id] as $uploadedFile) {
                    $stored = $this->storeFile($uploadedFile, $column->id);
                    $storedFiles[] = $stored;
                }

                $this->mergeContentFiles($column, $storedFiles);
                //                dd($storedFiles,$this->content,$this->content_attached);
            }
        }
        $this->content = $this->ledgerDefineRecord->normalizeByColumnDefine($this->content);
        $this->contentAttached = $this->ledgerDefineRecord->normalizeByColumnDefine($this->contentAttached);

        if ($this->ledgerId) {
            $this->storeLedgerDiff();

            $ledgerRecord = Ledger::find($this->ledgerId);
            $ledgerRecord->content = $this->content;
            $ledgerRecord->content_attached = $this->contentAttached;
            $ledgerRecord->modifier_id = Auth::user()->id;
            $ledgerRecord->save();

            $this->addAttachedFileRecord();
            //            dd($this->content);

            return redirect()->route('ledger.show', ['ledgerId' => $ledgerRecord->id])
                ->with('status', __('ledger.updated.success'));
        }
        abort(404);
    }

    /**
     * @param [object] $addingStoredFiles
     */
    public function mergeContentFiles(mixed $column, $addingStoredFiles): void
    {
        $addedFilenames = [];
        $addedFileContents = [];
        foreach ($addingStoredFiles as $stored) {
            $addedFilenames[$stored->hashedBaseName] = $stored->originalName;
            $addedFileContents[$stored->hashedBaseName] = null;
        }

        //既存ファイルの削除処理
        if (!empty($this->ledgerRecord->content[$column->id])) {
            /*
             * fileの保存状態
             * ['originalFilename'=>'savedFilePath']
             */
            $tmpContent = $this->ledgerRecord->content[$column->id] ?? [];
            $tmpContentAttached = $this->ledgerRecord->content_attached[$column->id] ?? [];

            $deletedBaseFilenames = [];
            //            パスがついているのでファイル名を取得
            foreach ($this->deletedContent[$column->id] as $deletedFilePath) {
                $deletedBaseFilenames[] = basename($deletedFilePath);
            }
            foreach ($this->ledgerRecord->content[$column->id] as $hashedBaseName => $filepath) {
                if (in_array($hashedBaseName, $deletedBaseFilenames, true)) {
                    unset($tmpContent[$hashedBaseName], $tmpContentAttached[$hashedBaseName]);
                    //実体ファイルを消したければここに削除処理を追加
                    AttachedFile::where('hashedbasename', $hashedBaseName)
                        ->where('ledger_id', $this->ledgerRecord->id)
                        ->where('ledger_define_id', $this->ledgerRecord->ledger_define_id)
                        ->where('column_id', $column->id)
                        ->delete();
                }
            }
            //以前保存したファイルとのマージ
            $this->content[$column->id] = array_merge($addedFilenames, $tmpContent);
            $this->contentAttached[$column->id] = array_merge($addedFileContents, $tmpContentAttached);
        } else {
            $this->content[$column->id] = $addedFilenames;
            $this->contentAttached[$column->id] = $addedFileContents;

        }
    }
    /*    public function mergeContentFiles(mixed $column): void
        {
            //新規登録したファイルの保存
            $filenames = $this->storeFile($column->id);
            $this->content[$column->id] = $filenames;

            //既存ファイルの削除処理
            if (!empty($this->ledgerRecord->content[$column->id])) {
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
        }*/

    private function getThumbnailUrl($filename): string
    {
        return Storage::url('Ledger/thumbs/' . basename($filename));
    }

    public function storeLedgerDiff(): void
    {
        $ledgerDiff = new LedgerDiff;
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
