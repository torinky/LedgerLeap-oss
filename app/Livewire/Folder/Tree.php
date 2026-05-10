<?php

namespace App\Livewire\Folder;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Repositories\WritableFolderRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Kalnoy\Nestedset\Collection as NestedSetCollection;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;

class Tree extends BaseLivewireComponent
{
    use InitializesTenantContext;

    public Collection $folders;

    #[Reactive]
    public $currentFolderId = null;

    #[Reactive]
    public ?array $selectedFolderIds = [];

    /**
     * parentComponentId なし（スタンドアロン）時に currentFolderChangeRequested イベントで
     * 更新するフォルダID。#[Reactive] の currentFolderId は親から渡される値のため
     * 子から直接 mutate できない。スタンドアロン時はこちらで追跡する。
     */
    public $standaloneFolderId = null;

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

    /**
     * parentComponentId なし（スタンドアロン）でツリーを使用している画面向け。
     * IndexManager 配下では Reactive プロパティとして親から渡されるため不要だが、
     * ledgerDefine 等の独立ページでは #[On] でイベントを受け取り standaloneFolderId を更新する。
     * #[Reactive] の currentFolderId は親から渡される値のため子から mutate できない。
     */
    #[On('currentFolderChangeRequested')]
    public function syncCurrentFolder($newFolderId): void
    {
        if (! $this->parentComponentId) {
            $this->standaloneFolderId = $newFolderId;
        }
    }

    /**
     * RecordsTable 等のメインコンポーネントがサーバーサイドで dispatch する
     * currentFolderChangedByMain イベントを受け取り、standaloneFolderId を同期する。
     * currentFolderChangeRequested はフロントエンドから発火されるが、
     * RecordsTable が同イベントをキャッチして changeCurrentFolder を実行した後に
     * このイベントを dispatch するため、両方をリッスンして確実に同期する。
     */
    #[On('currentFolderChangedByMain')]
    public function syncCurrentFolderFromMain($newFolderId): void
    {
        $this->standaloneFolderId = $newFolderId;
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
        // スタンドアロン（parentComponentId なし）の場合は standaloneFolderId を優先する。
        // IndexManager 配下では #[Reactive] の currentFolderId が親から渡される。
        $effectiveFolderId = $this->standaloneFolderId ?? $this->currentFolderId;

        Log::info('[Folder\Tree] rendering', [
            'currentFolderId' => $this->currentFolderId,
            'standaloneFolderId' => $this->standaloneFolderId,
            'effectiveFolderId' => $effectiveFolderId,
            'selectedFolderIds' => $this->selectedFolderIds,
        ]);

        // selectedFolderIds および effectiveFolderId の先祖フォルダを計算し、
        // ツリーで選択済みフォルダへのパスを自動展開するために使用する。
        $targetIds = array_filter(array_unique(array_merge(
            $this->selectedFolderIds,
            $effectiveFolderId ? [$effectiveFolderId] : []
        )));

        $selectedFolderAncestorIds = [];
        if (! empty($targetIds)) {
            $targetFolders = Folder::whereIn('id', $targetIds)->get(['id', '_lft', '_rgt']);
            if ($targetFolders->isNotEmpty()) {
                $selectedFolderAncestorIds = Folder::where(function ($query) use ($targetFolders) {
                    foreach ($targetFolders as $folder) {
                        $query->orWhere(function ($q) use ($folder) {
                            $q->where('_lft', '<', $folder->_lft)
                                ->where('_rgt', '>', $folder->_rgt);
                        });
                    }
                })->pluck('id')->toArray();
            }
        }

        return view('livewire.folder.tree', [
            'selectedFolderAncestorIds' => $selectedFolderAncestorIds,
            'effectiveFolderId' => $effectiveFolderId,
        ]);
    }
}
