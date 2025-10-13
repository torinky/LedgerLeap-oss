<?php

namespace App\Livewire\Folder;

use App\Http\Requests\Ledger\SearchRequest;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Livewire\Attributes\On;
use Livewire\Component;

class Tree extends Component
{
    use InitializesTenantContext;

    public \Illuminate\Database\Eloquent\Collection $folders;

    public int $currentFolderId;

    public array $selectedFolderIds;

    public array $manageableFolderIds;

    public array $writableFolderIds;

    public array $readableFolderIds;

    public function mount(SearchRequest $request, WritableFolderRepository $writableFolderRepository)
    {
        $this->currentFolderId = $request->currentFolderId();
        $this->selectedFolderIds = $request->folderId();
        $this->folders = Folder::whereIsRoot()->with('ledgerDefines')->get();

        $this->initializePermissions($writableFolderRepository);
    }

    #[On('permissions-changed')]
    public function refreshPermissions(WritableFolderRepository $writableFolderRepository)
    {
        $this->initializePermissions($writableFolderRepository);
    }

    private function initializePermissions(WritableFolderRepository $writableFolderRepository): void
    {
        $this->manageableFolderIds = $writableFolderRepository->getManageableFolderIds(auth()->user());
        $this->writableFolderIds = $writableFolderRepository->getWritableFolderIds(auth()->user());
        $this->readableFolderIds = $writableFolderRepository->getReadableFolderIds(auth()->user());
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
            if ($newFolderId == $this->currentFolderId && ! empty($this->selectedFolderIds)) {
                $this->selectedFolderIds = [];
            } else {
                $this->selectedFolderIds = Folder::descendantsAndSelf($newFolderId)->pluck('id')->toArray();
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
