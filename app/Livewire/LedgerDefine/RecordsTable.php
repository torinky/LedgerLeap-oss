<?php

namespace App\Livewire\LedgerDefine;

use App\Http\Requests\LedgerDefine\IndexRequest;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

class RecordsTable extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public $orderBy = 'title';

    public $orderAsc = true;

    public $folderRecords;

    public $breadcrumbs = [];

    public $selectedLedgerDefineIds = [];

    public $selectedFolderIds = [];

    public $currentFolderId;

    //    public $tenantId; // ここを追加

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
    #[On('folderSavedAndRefreshList')]
    public function render(IndexRequest $request)
    {
        $currentFolder = Folder::where('id', '=', $this->currentFolderId)->firstOrFail();

        return view('livewire.ledger-define.records-table', [
            'ledgerDefineRecords' => $this->ledgerDefineRecords,
            'currentFolder' => $currentFolder,
        ]);
    }

    /**
     * ツリーコンポーネントからのフォルダ選択を受け取る。
     * livewire:folder.tree が parentComponentId なしで呼ばれる場合、
     * Livewire.dispatch('currentFolderChangeRequested') で発火されるため
     * #[On] でリッスンする。
     */
    #[On('currentFolderChangeRequested')]
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

        // currentFolder が見つからない場合は、空の状態で初期化して処理を終了する
        if (is_null($currentFolder)) {
            $this->breadcrumbs = [];
            $this->folderRecords = collect();
            $this->ledgerDefineRecords = collect();

            return;
        }

        if (! empty($currentFolder)) {
            $this->breadcrumbs = $currentFolder->parent()->get();
        }
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)
            ->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc')
            ->get();

        $this->dispatch('navigation-end');
    }

    public function fixFolderTree()
    {
        Folder::fixtree();
        $this->prepareFolderAsset();
    }

    #[On('folderSavedAndRefreshList')]
    public function refreshList(): void
    {
        // リストを再読み込みする処理
        // 例: $this->folders = Folder::get()->toTree();
        //     または、このコンポーネント自体をリフレッシュ
        $this->dispatch('$refresh');
    }
}
