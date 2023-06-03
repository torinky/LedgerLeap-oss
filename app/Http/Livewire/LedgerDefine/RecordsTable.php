<?php

namespace App\Http\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\IndexRequest;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Livewire\Component;
use Livewire\WithPagination;

class RecordsTable extends Component
{
    use withPagination;

    public $perPage = 10;
    public $orderBy = 'id';
    public $orderAsc = true;
    public $folderRecords;

    public $breadcrumbs = [];

    public $selectedLedgerDefineIds = [];

    public $selectedFolderIds = [];

    public function mount(IndexRequest $request)
    {

        $currentFolder = Folder::where('id', '=', $request->folderId())->firstOrFail();

        $this->breadcrumbs = $currentFolder->parents();
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()->get();
        if ($this->folderRecords->count() == 0) {
            $this->folderRecords = collect([$currentFolder]);
        }
//        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $request->folderId())->get();

        if (!$currentFolder->isRoot()) {
            $this->selectedFolderIds = $this->folderRecords->pluck('id')->toArray();
//            $this->selectedLedgerDefineIds = $this->ledgerDefineRecords->pluck('id')->toArray();
        }
    }

    public function render(IndexRequest $request)
    {
        // checkboxのキーはサーバー側で変えるとブラウザに正しく反映されなくなる
        $this->selectedFolderIds = array_filter($this->selectedFolderIds, 'strlen');

        $descendantFolderIds = [];
        foreach ($this->selectedFolderIds as $selectedFolderId) {
            $descendantFolderIds = array_merge(
                Folder::whereDescendantOf($selectedFolderId)
                    ->pluck('id')->toArray()
                , $descendantFolderIds
            );
        }

        $selectedLedgerDefineIdsByFolderId = LedgerDefine::whereIn('folder_id',
            array_merge($this->selectedFolderIds, $descendantFolderIds)
        )
            ->pluck('id')->toArray() ?? [];


        $ledgerDefineIdsInRootFolder = [];
        $currentFolder = Folder::where('id', '=', $request->folderId())->firstOrFail();
        //現在地がルートなら所属の台帳を必ず表示
        if ($currentFolder->isRoot()) {
            $ledgerDefineIdsInRootFolder = LedgerDefine::where('folder_id', '=', $currentFolder->id)
                ->pluck('id')->toArray() ?? [];
        }

        $displayLedgerDefineIds = array_merge(
            $this->selectedLedgerDefineIds,
            $selectedLedgerDefineIdsByFolderId,
            $ledgerDefineIdsInRootFolder
        );

        //パンクズを作る
        $displayLedgerDefines = LedgerDefine::whereIn('id', $displayLedgerDefineIds)->with('folder')->get();

        $breadcrumbsPerLedgerDefine = [];
        foreach ($displayLedgerDefines as $displayLedgerDefine) {
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id] = $displayLedgerDefine->folder->parents();
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id][] = $displayLedgerDefine->folder;
        }


        return view('livewire.ledger-define.records-table', [
            'ledgerDefineRecords' =>
                LedgerDefine::whereIn('id', $displayLedgerDefineIds)
                    ->with('folder')
                    ->orderBy('id', 'asc')
                    ->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc')
                    ->simplePaginate($this->perPage),
            'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
            'currentFolder' => Folder::where('id', '=', $request->folderId())->firstOrFail(),

        ]);
    }
}
