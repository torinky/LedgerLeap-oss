<?php

namespace App\Livewire\Folder;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Tree extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public \Illuminate\Database\Eloquent\Collection $folders;

    #[Reactive]
    public $currentFolderId = null;

    #[Reactive]
    public array $selectedFolderIds = [];

    public array $writableFolderIds;

    public array $manageableFolderIds;

    public array $readableFolderIds;

    public $parentComponentId;

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
        $this->manageableFolderIds = $writableFolderRepository->getManageableFolderIds(Auth::user());
        $this->writableFolderIds = $writableFolderRepository->getWritableFolderIds(Auth::user());
        $this->readableFolderIds = $writableFolderRepository->getReadableFolderIds(Auth::user());
    }



    public function render()
    {
        Log::info('[Folder\Tree] rendering', [
            'currentFolderId' => $this->currentFolderId,
            'selectedFolderIds' => $this->selectedFolderIds
        ]);
        return view('livewire.folder.tree');
    }
}
