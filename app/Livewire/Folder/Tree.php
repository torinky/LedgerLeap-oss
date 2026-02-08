<?php

namespace App\Livewire\Folder;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

        // 全てのフォルダーを階層構造でEager Loadし、各フォルダーの台帳定義もロード
        // Nestedsetの機能を使って、ルートから全子孫を一度に取得
        $this->folders = Folder::whereIsRoot()
            ->with(['ledgerDefines', 'children' => function ($query) {
                $query->with('ledgerDefines');
            }])
            ->get();

        // 全フォルダーを取得して、再帰的にledgerDefinesをEager Load
        // これにより、N+1問題を回避
        $this->eagerLoadDescendants($this->folders);

        $this->initializePermissions($writableFolderRepository);
    }

    /**
     * 全ての子孫フォルダーのledgerDefinesを再帰的にEager Load
     */
    private function eagerLoadDescendants($folders)
    {
        foreach ($folders as $folder) {
            if ($folder->children && $folder->children->count() > 0) {
                // 子フォルダーのledgerDefinesをロード
                $folder->children->load('ledgerDefines');

                // 再帰的に子孫を処理
                $this->eagerLoadDescendants($folder->children);
            }
        }
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
