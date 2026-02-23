<?php

namespace App\Livewire\Folder;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Kalnoy\Nestedset\Collection as NestedSetCollection;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

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

        // kalnoy/nestedset の descendants リレーションで全子孫を一括取得。
        // 再帰的 Eager Load (eagerLoadDescendants) を廃止し、
        // クエリ数を階層の深さに依存しない固定値に最適化（Sprint 4）。
        $this->folders = Folder::whereIsRoot()
            ->with(['ledgerDefines', 'descendants' => function ($query) {
                $query->with('ledgerDefines')->defaultOrder();
            }])
            ->get();

        // descendants で取得した全子孫を NestedSetCollection::linkNodes() で
        // children リレーションにポピュレートする。
        // ビューは $folder->children を再帰的に使って描画するため必須。
        foreach ($this->folders as $root) {
            $allNodes = new NestedSetCollection(array_merge([$root], $root->descendants->all()));
            $allNodes->linkNodes();
        }

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
            'selectedFolderIds' => $this->selectedFolderIds,
        ]);

        return view('livewire.folder.tree');
    }
}
