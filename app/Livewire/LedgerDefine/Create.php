<?php

namespace App\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Livewire\Component;
use Mary\Traits\Toast;

class Create extends Component
{
    use InitializesTenantContext, Toast;

    public $ledgerDefineRecord;

    public $folderRecords = [];

    public $folderIdNameMap = [];

    public $title;

    public $parentFolderId;

    public function render()
    {
        return view('livewire.ledger-define.create')
            ->layout('layouts.app', ['title' => 'SETTING | DocumentCabinet']);

    }

    public function mount(createRequest $request)
    {
        $ledgerDefine = new LedgerDefine;
        $this->ledgerDefineRecord = $ledgerDefine;

        $this->title = $request->title;
        $this->parentFolderId = $request->folderId();
        //        dd($this->parentFolderId);
        $this->folderRecords = [];
        $nodes = $this->folderRecords = Folder::get()->toTree();
        $traverse = function ($categories, $prefix = '-') use (&$traverse) {
            foreach ($categories as $category) {
                $category->title = $prefix.' '.$category->title;
                $this->folderRecords[] = $category;

                $traverse($category->children, $prefix.'-');
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

    }

    public function store()
    {
        $this->ledgerDefineRecord->title = $this->title;
        $this->ledgerDefineRecord->folder_id = $this->parentFolderId;
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->creator_id = auth()->id();
        $this->ledgerDefineRecord->column_define = [];
        $this->ledgerDefineRecord->save();

        //        return redirect()->route('ledgerDefine.edit', ['ledgerDefineId' => $this->ledgerDefineRecord->id, 'fromCreate' => true]);
        // イベントを発行
        //        $this->dispatch('ledgerDefineRecordStored');
        $this->success(__('ledger.has_been_created'),
            redirectTo: route('ledgerDefine.edit', [
                'ledgerDefineId' => $this->ledgerDefineRecord->id,
                'fromCreate' => true,
            ])
        );

    }
}
