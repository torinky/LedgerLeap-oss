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

//    public array $selectedLedgerIds;


    public function mount(searchRequest $request)
    {
        $this->currentFolderId = $request->folderId();
        $this->folders = Folder::all()->toTree();
    }

    #[On('currentFolderChangedByMain')]
    public function changeCurrentFolderByMain($newFolderId, $newSelectedFolderIds)
    {
        $this->currentFolderId = $newFolderId;
        $this->selectedFolderIds = $newSelectedFolderIds;

    }

    #[On('selectedFolderChangedByMain')]
    public function selectedFolderByMain($newSelectedFolderIds)
    {
        $this->selectedFolderIds = $newSelectedFolderIds;
    }

    public function changeCurrentFolder($newFolderId)
    {
        if ($newFolderId == 1) {
            $this->selectedFolderIds = [];
        } else {
            if ($newFolderId == $this->currentFolderId && !empty($this->selectedFolderIds)) {
                $this->selectedFolderIds = [];
            } else {
                $this->selectedFolderIds = Folder::whereDescendantOf($newFolderId)->pluck('id')->toArray();
            }
        }
        $this->currentFolderId = $newFolderId;
        $this->dispatch('currentFolderChangedByTree', newFolderId: $this->currentFolderId, newSelectedFolderIds: $this->selectedFolderIds);

    }

    public function render()
    {
        return view('livewire.folder.tree');
    }
}
