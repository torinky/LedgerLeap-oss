<?php

namespace App\Livewire\LedgerDefine;

use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Livewire\Component;

class Edit extends Component
{
    public $ledgerDefineRecord;

    public $folderRecords = [];

    public $folderIdNameMap = [];

    public $title;

    public $parentFolderId;

    public function render()
    {
        return view('livewire.ledger-define.edit');
    }

    public function mount(request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->title = $this->ledgerDefineRecord->title;


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
            return (object)[$folderRecord->id => ['id' => $folderRecord->id, 'name' => $folderRecord->title]];
        });

        $this->parentFolderId = $this->ledgerDefineRecord->folderId;

//        dd($this->folderRecords,$this->folderIdNameMap);


    }

    public function applyTitle()
    {
        $this->ledgerDefineRecord->title = $this->title;
        $this->store();
    }

    /**
     * @return void
     */
    private function store(): void
    {
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();
    }

    public function applyParentFolder()
    {
        $this->ledgerDefineRecord->folder_id = $this->parentFolderId;
        $this->store();
    }

}
