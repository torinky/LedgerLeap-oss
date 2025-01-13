<?php

namespace App\Livewire\LedgerDefine;

use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Livewire\Component;
use Mary\Traits\Toast;

class Edit extends Component
{
    use Toast;

    public $ledgerDefineRecord;

    public $folderRecords = [];

    public $createDescription;
    public $detailDescription;
    public $listDescription;
    public $folderIdNameMap = [];

    public $title;

    public $parentFolderId;

    public $descriptionGroup = 'createDescription';
    public function render()
    {
        return view('livewire.ledger-define.edit');
    }

    public function mount(request $request)
    {
        if ($request->input('fromCreate')) {
            $this->dispatch('reloadParentWindow');
        }

        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrNew();

        $this->title = $this->ledgerDefineRecord->title;
        $this->parentFolderId = $this->ledgerDefineRecord->folder_id;
        $this->createDescription = $this->ledgerDefineRecord->create_description;
        $this->listDescription = $this->ledgerDefineRecord->list_description;
        $this->detailDescription = $this->ledgerDefineRecord->detail_description;

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
    }

    public function store(): void
    {
        $this->ledgerDefineRecord->title = $this->title;
        $this->ledgerDefineRecord->folder_id = $this->parentFolderId;
        $this->ledgerDefineRecord->create_description = $this->createDescription;
        $this->ledgerDefineRecord->list_description = $this->listDescription;
        $this->ledgerDefineRecord->detail_description = $this->detailDescription;
        $this->ledgerDefineRecord->modifier_id = auth()->id();
        $this->ledgerDefineRecord->save();
        $this->success(__('ledger.has_been_updated'));
        $this->dispatch('ledgerDefineRecordStored');

        // イベントを発行
        //        $this->dispatch('ledgerDefineRecordStored');
    }

    public function toggleDescriptionGroup($name)
    {
        $this->descriptionGroup = $name;
        $this->dispatch('toggleDescriptionGroup', name: $name);

    }
}
