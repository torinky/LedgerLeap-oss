<?php

namespace App\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\IndexRequest;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class RecordsTable extends Component
{
    public $orderBy = 'title';

    public $orderAsc = true;

    public $folderRecords;

    public $breadcrumbs = [];

    public $selectedLedgerDefineIds = [];

    public $selectedFolderIds = [];

    public $currentFolderId;

    private $ledgerDefineRecords;

    /**
     * @return void
     */
    public function mount(IndexRequest $request)
    {
        $this->currentFolderId = $request->folderId();
        $this->prepareFolderAsset();

    }

    /**
     * @return Application|Factory|View|\Illuminate\Foundation\Application
     */
    public function render(IndexRequest $request)
    {
        $currentFolder = Folder::where('id', '=', $this->currentFolderId)->firstOrFail();

        return view('livewire.ledger-define.records-table', [
            'ledgerDefineRecords' => $this->ledgerDefineRecords,
            'currentFolder' => $currentFolder,
        ]);
    }

    public function changeCurrentFolder($newFolderId)
    {
        $this->currentFolderId = $newFolderId;
        $this->dispatch('currentFolderChangedByMain', newFolderId: $this->currentFolderId, newSelectedFolderIds: []);

        $this->prepareFolderAsset();
    }

    /**
     * @return void
     */
    #[On('currentFolderChangedByTree')]
    public function currentFolderChangedByTree($newFolderId, $newSelectedFolderIds)
    {
        $this->currentFolderId = $newFolderId;

        $this->prepareFolderAsset();
    }

    public function prepareFolderAsset(): void
    {
        $currentFolder = Folder::where('id', '=', $this->currentFolderId)->first();

        if (!empty($currentFolder)) {
            $this->breadcrumbs = $currentFolder->parents();
        }
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)
            ->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc')
            ->get();

    }

    public function fixFolderTree()
    {
        Folder::fixtree();
        $this->prepareFolderAsset();
    }
}
