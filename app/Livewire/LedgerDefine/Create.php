<?php

namespace App\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use Toast;

    public $ledgerDefineRecord;

    public $folderRecords = [];

    public $folderIdNameMap = [];

    public $title;

    public $parentFolderId;

    public mixed $initialFolderId;

    public function render()
    {
        return view('livewire.ledger-define.create')
            ->layout('layouts.app', ['title' => 'SETTING | DocumentCabinet']);

    }

    public function mount(createRequest $request)
    {
        $ledgerDefine = new LedgerDefine;
        //                $ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine;
        //        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->title = $request->title;
        $this->parentFolderId = $request->folder_id ?? Folder::root()->pluck('id')[0];
        //        dd($this->parentFolderId);
        $this->folderRecords = [];
        $nodes = $this->folderRecords = Folder::get()->toTree();
        $traverse = function ($categories, $prefix = '-') use (&$traverse) {
            foreach ($categories as $category) {
                $category->title = $prefix . ' ' . $category->title;
                $this->folderRecords[] = $category;

                $traverse($category->children, $prefix . '-');
            }
        };

        $traverse($nodes);
        $this->folderRecords = collect($this->folderRecords);

        $this->folderIdNameMap = $this->folderRecords->mapWithKeys(function ($folderRecord) {
            $selected = $folderRecord->id == $this->parentFolderId ? true : false;

            return [
                $folderRecord->id => [
                    'id' => $folderRecord->id,
                    'name' => $folderRecord->title,
                    'selected' => $selected,
                ],
            ];
        });

        $this->initialFolderId = null;

    }

    public function store()
    {
        $this->ledgerDefineRecord->title = $this->title;
        $this->ledgerDefineRecord->folder_id = $this->parentFolderId;
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->creator_id = auth()->id();
        $this->ledgerDefineRecord->column_define = [];
        $this->ledgerDefineRecord->save();

//        jsが正しく初期化されない
//        $redirectTo = route('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineRecord->id, 'fromCreate' => true]);
//        $this->success(__('ledger.has_been_created'), redirectTo:$redirectTo);
//        $this->success(__('ledger.has_been_created'));
        return redirect()->route('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineRecord->id, 'fromCreate' => true]);
        // イベントを発行
        //        $this->dispatch('ledgerDefineRecordStored');
    }
}
