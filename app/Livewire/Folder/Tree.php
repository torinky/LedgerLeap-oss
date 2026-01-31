<?php

namespace App\Livewire\Folder;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive; // 追加

class Tree extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public \Illuminate\Database\Eloquent\Collection $folders;

    #[Reactive]
    public $currentFolderId;

    #[Reactive]
    public array $selectedFolderIds;

    public array $manageableFolderIds;

    public array $writableFolderIds;

    public array $readableFolderIds;

    public function mount(WritableFolderRepository $writableFolderRepository)
    {
        // currentFolderId と selectedFolderIds は親 (IndexManager) から Reactive プロパティとして
        // 渡されるため、ここでの再初期化は不要。また、Reactive プロパティを子で書き換えると
        // CannotMutateReactivePropException の原因になる。
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

    public function changeCurrentFolder($newFolderId)
    {
        $this->dispatch('currentFolderChangeRequested', newFolderId: $newFolderId);
    }

    public function render()
    {
        \Log::info('[Folder\Tree] rendering', [
            'currentFolderId' => $this->currentFolderId,
            'selectedFolderIds' => $this->selectedFolderIds
        ]);
        return view('livewire.folder.tree');
    }
}
