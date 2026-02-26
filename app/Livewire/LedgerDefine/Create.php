<?php

namespace App\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasFolderTree;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\LedgerDefine;
use Mary\Traits\Toast;

class Create extends BaseLivewireComponent
{
    use HasFolderTree, InitializesTenantContext, Toast;

    public $ledgerDefineRecord;

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
        $this->initializeFolderTree($this->parentFolderId);
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
