<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest; // 追加
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use Livewire\Attributes\Url;
use Mary\Traits\Toast;

class IndexManager extends BaseLivewireComponent
{
    use InitializesTenantContext, Toast;

    #[Url(as: 'q')]
    public $search = '';

    public $orderBy = 'composite_score';

    public $orderAsc = false;

    public $filterStatus = '';

    #[Url(as: 'fi')]
    public $filter = [];

    #[Url(as: 'l')]
    public $selectedLedgerDefineIds = [];

    #[Url(as: 'f')]
    public $selectedFolderIds = [];

    #[Url(as: 'cf')]
    public $currentFolderId;

    #[Url(as: 'dl')]
    public int $displayLevel = 1;

    #[Url(as: 'sem', history: true)]
    public bool $useSemanticSearch = false;

    public function mount(SearchRequest $request, $folderId = null, $defineId = null)
    {
        $this->currentTenantId = tenant()?->id;

        // 初期表示レベルのバリデーション
        if (! in_array($this->displayLevel, [1, 2, 3])) {
            $this->displayLevel = 1;
        }

        // RecordsTable の mount ロジックの一部を移行
        // 検索キーワードの初期化
        $search = $request->keyword();
        if (empty($this->search) && ! empty($search)) {
            $this->search = $search;
        } elseif (empty($this->search)) {
            $this->search = session()->get('search', '');
        }

        $this->filter = $request->filter ?? $this->filter;

        // 現在のフォルダーIDを初期化
        // ルートパラメータ {folderId} を最優先
        if ($folderId) {
            $this->currentFolderId = $folderId;
            $this->selectedFolderIds = [$folderId];
        } elseif (empty($this->selectedFolderIds) && $request->folderId()) {
            $this->selectedFolderIds = [$request->folderId()];
        }

        if (empty($this->currentFolderId)) {
            $this->currentFolderId = $request->currentFolderId();
        }

        // もし台帳IDが指定されていれば、選択済みリストに追加
        // ルートパラメータ {defineId} を最優先
        if ($defineId) {
            $this->selectedLedgerDefineIds = [$defineId];
        } elseif (empty($this->selectedLedgerDefineIds) && $request->ledgerDefineId()) {
            $this->selectedLedgerDefineIds = [$request->ledgerDefineId()];
        }
    }

    public function render()
    {
        return view('livewire.ledger.index-manager')
            ->layout('layouts.appWithDrawer', ['title' => __('ledger.records_title')]);
    }
}
