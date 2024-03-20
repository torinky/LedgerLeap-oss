<?php

namespace App\Livewire\Folder;

use App\Http\Requests\Ledger\SearchRequest;
use App\Models\Folder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Route;
use Livewire\Attributes\On;
use Livewire\Component;

class Tree extends Component
{
    public \Fureev\Trees\Collection|Collection $folders;
    /**
     * @var Route|int|mixed|object|string
     */
    public int $currentFolderId;
    public array $selectedFolderIds;
    public array $selectedLedgerIds;
    public array $selectedFolderChildrenIds;

//    protected $listeners = ['currentFolderChangedByMain'];

    public function mount(searchRequest $request)
    {
        $this->currentFolderId = $request->folderId();
        $this->folders = Folder::all()->toTree();
    }

    #[On('currentFolderChangedByMain')]
    public function changeCurrentFolderChangedByMain($newFolderId, $newSelectedFolderIds)
    {
        $this->currentFolderId = $newFolderId;
        $this->selectedFolderIds = $newSelectedFolderIds;

        $this->selectedFolderChildrenIds = [];
        $newSelectedFolderChildren = Folder::wherein('id', $newSelectedFolderIds)->get();
        foreach ($newSelectedFolderChildren as $newSelectedFolderChild) {
            $this->selectedFolderChildrenIds = array_merge($this->selectedFolderChildrenIds, $newSelectedFolderChild->children()->pluck('id')->toArray());
        }
    }

    public function changeCurrentFolder($newFolderId)
    {
        $this->currentFolderId = $newFolderId;
        $this->dispatch('currentFolderChangedByTree', newFolderId: $this->currentFolderId);

    }

    public function render()
    {
        return view('livewire.folder.tree');
    }
}
