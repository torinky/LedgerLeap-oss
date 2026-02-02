<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest; // 追加
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService; // 追加
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as FacadesSchema;
use Livewire\Attributes\On;
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

    public $perPage = 100;

    #[Url(as: 'sem', history: true)]
    public bool $useSemanticSearch = false;

    public $useSynonym = true;

    public $useTechnicalTerm = true;

    public string $orderByLabel = '';

    public array $defaultSortColumns = [];

    public bool $hasWorkflowEnabled = false;

    public $currentTenantId;

    public $keywords = [];

    public $synonyms = [];

    public $totalRecords = 0;

    public $highlights = [];

    // フォルダーアセット関連
    public $breadcrumbs = [];

    public $folderRecords;

    public $ledgerDefineRecords;

    public $currentFolder;

    public $currentUserPermissionForFolder;

    // セマンティック検索ON前の同義語トグル状態を保存
    private $savedUseSynonymState = null;

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
            $this->selectedFolderIds = Folder::descendantsAndSelf($folderId)->pluck('id')->toArray();
            $this->selectedLedgerDefineIds = LedgerDefine::whereIn('folder_id', $this->selectedFolderIds)->pluck('id')->toArray();
        } elseif (empty($this->selectedFolderIds) && $request->folderId()) {
            $this->selectedFolderIds = Folder::descendantsAndSelf($request->folderId())->pluck('id')->toArray();
            $this->selectedLedgerDefineIds = LedgerDefine::whereIn('folder_id', $this->selectedFolderIds)->pluck('id')->toArray();
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

        // currentFolderId が未だに空（または不正）な場合の最終的なフォールバック
        if (empty($this->currentFolderId) || ! Folder::find($this->currentFolderId)) {
            $rootFolder = Folder::root()->first();
            if ($rootFolder) {
                $this->currentFolderId = $rootFolder->id;
            }
        }

        $this->prepareFolderAsset();
        $this->updateSearchMetadata();
        $this->initSearchContext();
    }

    public function prepareFolderAsset()
    {
        // 既に準備済みの場合はスキップ（重複実行を防ぐ）
        if (isset($this->currentFolder) && $this->currentFolder && $this->currentFolder->id === $this->currentFolderId) {
            return;
        }

        $this->currentFolder = Folder::with('ancestors')->find($this->currentFolderId);
        if (! $this->currentFolder) {
            return;
        }

        $this->currentUserPermissionForFolder = app(\App\Services\PermissionService::class)->getCurrentUserHighestPermission($this->currentFolder->id, 'Folder');

        $this->breadcrumbs = $this->currentFolder->ancestors->all();
        $this->breadcrumbs[] = $this->currentFolder;

        $this->folderRecords = $this->currentFolder->children()
            ->withCount(['ledgerDefines']) // 追加: 子フォルダごとの台帳定義数
            ->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)
            ->withCount(['ledgers']) // 追加: 台帳定義ごとの台帳レコード数
            ->get();
    }

    public function initSearchContext()
    {
        $synonymServiceConfig = new SynonymServiceConfig([
            'useSynonym' => $this->useSynonym,
            'useTechnicalTerm' => $this->useTechnicalTerm,
        ]);
        $synonymService = new SynonymService($synonymServiceConfig);
        $searchContext = new SearchContext($synonymService);

        $searchContext->setSearch($this->search);
        $searchContext->setFilter($this->filter);

        $this->keywords = $searchContext->keywords ?? [];
        $this->highlights = $searchContext->highlights ?? [];
        $this->synonyms = $searchContext->synonyms ?? [];
    }

    public function updateSearchMetadata()
    {
        // ワークフロー対応の判定
        $this->hasWorkflowEnabled = LedgerDefine::whereIn('id', $this->selectedLedgerDefineIds)
            ->where('workflow_enabled', true)
            ->exists();

        if (empty($this->selectedLedgerDefineIds) && ! empty($this->currentFolderId)) {
            // フォルダ内すべての台帳を対象にする場合のチェック (簡易実装)
            $this->hasWorkflowEnabled = LedgerDefine::where('folder_id', $this->currentFolderId)
                ->where('workflow_enabled', true)
                ->exists();
        }

        // デフォルトソート設定の読み込み
        $this->defaultSortColumns = [];
        if (count($this->selectedLedgerDefineIds) === 1) {
            $singleLedgerDefine = LedgerDefine::find(head($this->selectedLedgerDefineIds));
            if ($singleLedgerDefine) {
                $this->defaultSortColumns = collect($singleLedgerDefine->column_define)
                    ->filter(fn ($column) => isset($column->sort_index))
                    ->sortBy('sort_index')
                    ->map(fn ($column) => $column->toArray()) // (array) ではなく toArray() を使用
                    ->values()
                    ->toArray();

                if (! empty($this->defaultSortColumns) && $this->orderBy === 'composite_score') {
                    $this->orderBy = 'default';
                    $this->orderAsc = true;
                }
            }
        }

        // composite_scoreカラムの存在確認
        if ($this->orderBy !== 'default' && ! FacadesSchema::hasColumn('ledgers', 'composite_score')) {
            $this->orderBy = 'id';
        }

        $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);
    }

    public function updatedOrderBy($value)
    {
        if ($this->getStandardSortLabel($value) === '' && $value === $this->orderBy) {
            $this->orderBy = 'composite_score';
        }
        $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);
    }

    public function updatedSearch()
    {
        $this->initSearchContext();
    }

    public function updatedFilter()
    {
        $this->initSearchContext();
    }

    public function updatedUseSynonym()
    {
        $this->initSearchContext();
    }

    public function updatedUseTechnicalTerm()
    {
        $this->initSearchContext();
    }

    public function updatedUseSemanticSearch($value)
    {
        if ($value) {
            $this->savedUseSynonymState = $this->useSynonym;
            $this->useSynonym = false;
        } else {
            if ($this->savedUseSynonymState !== null) {
                $this->useSynonym = $this->savedUseSynonymState;
                $this->savedUseSynonymState = null;
            }
            if ($this->orderBy === 'semantic_score') {
                $this->orderBy = 'composite_score';
                $this->orderByLabel = $this->getStandardSortLabel('composite_score');
            }
        }
    }

    private function getStandardSortLabel(string $columnName): string
    {
        return match ($columnName) {
            'composite_score' => __('ledger.scoring.score'),
            'created_at' => __('ledger.created_at'),
            'updated_at' => __('ledger.updated_at'),
            'semantic_score' => __('ledger.semantic_score_sort'),
            'default' => $this->getDefaultSortLabel(),
            default => $this->getColumnLabel($columnName),
        };
    }

    /**
     * 動的なカラム名のラベルを取得する
     */
    private function getColumnLabel(string $columnName): string
    {
        // content->ID 形式のカラム名からラベルを解決
        if (str_starts_with($columnName, 'content->')) {
            // 単一台帳選択時のみ具体的な項目名を表示
            if (count($this->selectedLedgerDefineIds) === 1) {
                $columnId = str_replace('content->', '', $columnName);
                $singleLedgerDefine = LedgerDefine::find(head($this->selectedLedgerDefineIds));

                if ($singleLedgerDefine) {
                    $column = collect($singleLedgerDefine->column_define)
                        ->first(fn ($col) => (string) $col->id === (string) $columnId);

                    if ($column) {
                        return $column->name;
                    }
                }
            }

            // 複数台帳選択時や項目が見つからない場合は汎用的なラベルを表示
            return __('ledger.column.custom_column_sort');
        }

        return '';
    }

    private function getDefaultSortLabel(): string
    {
        $label = __('ledger.default_sort_order');

        // 単一台帳選択時のみ具体的な項目名を表示
        if (count($this->selectedLedgerDefineIds) === 1 && ! empty($this->defaultSortColumns)) {
            $columnNames = collect($this->defaultSortColumns)->pluck('name')->implode(', ');
            $label .= " ({$columnNames})";
        }

        return $label;
    }

    public function setDisplayLevel(int $level): void
    {
        if (in_array($level, [1, 2, 3])) {
            $this->displayLevel = $level;
        }
    }

    #[On('sortRequested')]
    public function sort($columnName, $columnLabel = null)
    {
        $this->orderBy = $columnName;
        $this->orderAsc = ! $this->orderAsc;

        // 手動でラベルが渡されない（ヘッダーからのソートなど）場合、
        // getStandardSortLabel 内で台帳の選択状態に基づいたラベルを取得する
        $this->orderByLabel = $columnLabel ?? $this->getStandardSortLabel($columnName);
    }

    #[On('displayLevelRequested')]
    public function updateDisplayLevel($level)
    {
        $this->setDisplayLevel($level);
    }

    public function updatedPerPage()
    {
        $this->dispatch('perPageUpdated', perPage: $this->perPage);
    }

    #[On('currentFolderChangeRequested')]
    public function changeCurrentFolder($newFolderId)
    {
        Log::info('[IndexManager] Received currentFolderChangeRequested', ['newFolderId' => $newFolderId]);
        Log::info('[IndexManager] changeCurrentFolder called', ['newFolderId' => $newFolderId]);
        if ($newFolderId == 1) {
            $this->selectedFolderIds = [];
            $this->selectedLedgerDefineIds = [];
        } else {
            if ($newFolderId == $this->currentFolderId && ! empty($this->selectedFolderIds)) {
                $this->selectedFolderIds = [];
            } else {
                $this->selectedFolderIds = Folder::descendantsAndSelf($newFolderId)->pluck('id')->sort()->values()->toArray();
                $this->selectedLedgerDefineIds = LedgerDefine::whereIn('folder_id', $this->selectedFolderIds)->pluck('id')->sort()->values()->toArray();
            }
        }
        $this->currentFolderId = $newFolderId;
        $this->prepareFolderAsset();
        $this->updateSearchMetadata();
    }

    #[On('focusLedgerDefineRequested')]
    public function focusLedgerDefine($defineId)
    {
        $this->selectedLedgerDefineIds = [$defineId];
        $this->updateSearchMetadata();
    }

    #[On('folderIdToggled')]
    public function toggleFolderId($folderId)
    {
        if ($folderId == 1) {
            $this->selectedFolderIds = [];
            $this->selectedLedgerDefineIds = [];
        } elseif (in_array($folderId, $this->selectedFolderIds)) {
            $removeFolderIds = Folder::descendantsAndSelf($folderId)->pluck('id')->toArray();
            $this->selectedFolderIds = array_values(array_diff($this->selectedFolderIds, $removeFolderIds));

            $removeLedgerRecordIds = LedgerDefine::whereIn('folder_id', $removeFolderIds)->pluck('id')->toArray();
            $this->selectedLedgerDefineIds = array_values(array_diff($this->selectedLedgerDefineIds, $removeLedgerRecordIds));
        } else {
            $mergingFolderIds = Folder::descendantsAndSelf($folderId)->pluck('id')->toArray();
            $this->selectedFolderIds = array_merge($this->selectedFolderIds, $mergingFolderIds);
            $this->selectedLedgerDefineIds = array_merge($this->selectedLedgerDefineIds, LedgerDefine::whereIn('folder_id', $mergingFolderIds)->pluck('id')->toArray());
        }

        sort($this->selectedFolderIds);
        sort($this->selectedLedgerDefineIds);

        $this->updateSearchMetadata();
    }

    #[On('ledgerDefineIdToggled')]
    public function toggleLedgerDefineId($ledgerDefineId)
    {
        if (in_array($ledgerDefineId, $this->selectedLedgerDefineIds)) {
            $this->selectedLedgerDefineIds = array_values(array_diff($this->selectedLedgerDefineIds, [$ledgerDefineId]));
        } else {
            $this->selectedLedgerDefineIds[] = $ledgerDefineId;
        }

        sort($this->selectedLedgerDefineIds);

        $this->updateSearchMetadata();
    }

    #[On('recordsUpdated')]
    public function updateRecordCount($total)
    {
        $this->totalRecords = $total;
    }

    #[On('openPermissionModal')]
    public function openPermissionModal(string $resourceType, int $resourceId, string $title): void
    {
        $this->dispatch('openPermissionModalRequested', resourceType: $resourceType, resourceId: $resourceId, title: $title);
    }

    #[On('openActivityModal')]
    public function openActivityModal(string $resourceType, int $resourceId, string $title): void
    {
        $this->dispatch('openActivityModalRequested', resourceType: $resourceType, resourceId: $resourceId, title: $title);
    }

    /**
     * 子コンポーネント (RecordsTable 等) からフィルタが更新された際の通知
     */
    #[On('filterUpdated')]
    public function recordFilterUpdate($data)
    {
        $this->filter = $data;
        $this->initSearchContext();
    }

    /**
     * 子コンポーネントからフィルタを直接更新する (パフォーマンス向上のため $parent.method() で呼ばれる)
     */
    public function updateFilterFromChild($columnId, $value, $defineId = null)
    {
        $this->filter[$columnId] = $value;
        $this->initSearchContext();
    }

    public function render()
    {
        Log::info('IndexManager render', [
            'search' => $this->search,
            'useSemanticSearch' => $this->useSemanticSearch,
            'currentFolderId' => $this->currentFolderId,
            'selectedFolderIds' => $this->selectedFolderIds,
            'selectedLedgerDefineIds' => $this->selectedLedgerDefineIds,
            'orderBy' => $this->orderBy,
            'filterStatus' => $this->filterStatus,
        ]);

        return view('livewire.ledger.index-manager', [
            'keywords' => $this->keywords ?? [],
            'synonyms' => $this->synonyms ?? [],
            'totalRecords' => $this->totalRecords ?? 0,
            'highlights' => $this->highlights ?? [],
            'orderByLabel' => $this->orderByLabel,
            'defaultSortColumns' => $this->defaultSortColumns,
            'hasWorkflowEnabled' => $this->hasWorkflowEnabled,
            'breadcrumbs' => $this->breadcrumbs,
            'folderRecords' => $this->folderRecords,
            'ledgerDefineRecords' => $this->ledgerDefineRecords,
            'currentFolder' => $this->currentFolder,
            'currentUserPermissionForFolder' => $this->currentUserPermissionForFolder,
        ])->layout('layouts.appWithDrawer', ['title' => __('ledger.records_title')]);
    }
}
