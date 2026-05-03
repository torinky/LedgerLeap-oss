<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest; // 追加
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasSortingLabels;
use App\Livewire\Traits\InitializesTenantContext;
use App\Livewire\Traits\LogPerformance;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Services\ConfidentialityLevelService;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\PermissionService; // 追加
use App\Services\SynonymService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema as FacadesSchema;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Mary\Traits\Toast;

class IndexManager extends BaseLivewireComponent
{
    use HasSortingLabels, InitializesTenantContext, LogPerformance, Toast;

    #[Url(as: 'q')]
    public $search = '';

    #[Url(as: 'sort')]
    public $orderBy = 'composite_score';

    #[Url(as: 'dir')]
    public $orderAsc = false;

    #[Url(as: 'status')]
    public $filterStatus = '';

    #[Url(as: 'filter')]
    public $filter = [];

    #[Url(as: 'l')]
    public $selectedLedgerDefineIds = [];

    #[Url(as: 'f')]
    public $selectedFolderIds = [];

    #[Url(as: 'cf')]
    public $currentFolderId;

    #[Url(as: 'dl')]
    public int $displayLevel = 1;

    #[Url(as: 'pp')]
    public $perPage = 100;

    #[Url(as: 'sem', history: true)]
    public bool $useSemanticSearch = false;

    #[Url(as: 'syn')]
    public $useSynonym = true;

    #[Url(as: 'tt')]
    public $useTechnicalTerm = true;

    public string $orderByLabel = '';

    public array $defaultSortColumns = [];

    public bool $hasWorkflowEnabled = false;

    public $currentTenantId;

    public $keywords = [];

    public $tags = [];

    public $synonyms = [];

    public $totalRecords = 0;

    public bool $totalRecordsLoaded = false;

    public $highlights = [];

    public ?int $activeLedgerDefineId = null;

    // フォルダーアセット関連
    #[Computed]
    public function currentFolder()
    {
        if (empty($this->currentFolderId)) {
            return null;
        }

        return Folder::with('ancestors')->find($this->currentFolderId);
    }

    #[Computed]
    public function currentUserPermissionForFolder()
    {
        if (! $this->currentFolder) {
            return null;
        }

        return app(PermissionService::class)->getCurrentUserHighestPermission($this->currentFolder->id, 'Folder');
    }

    #[Computed]
    public function breadcrumbs()
    {
        if (! $this->currentFolder) {
            return [];
        }

        $breadcrumbs = $this->currentFolder->ancestors->all();
        $breadcrumbs[] = $this->currentFolder;

        return $breadcrumbs;
    }

    #[Computed]
    public function folderRecords()
    {
        if (! $this->currentFolder) {
            return collect();
        }

        return $this->currentFolder->children()
            ->addSelect(['ledger_defines_count' => LedgerDefine::selectRaw('count(*)')
                ->whereIn('folder_id', function ($query) {
                    $query->select('id')
                        ->from('folders as f2')
                        ->whereColumn('f2._lft', '>=', 'folders._lft')
                        ->whereColumn('f2._lft', '<', 'folders._rgt');
                }),
            ])
            ->get();
    }

    #[Computed]
    public function ledgerDefineRecords()
    {
        if (empty($this->currentFolderId)) {
            return collect();
        }

        return LedgerDefine::where('folder_id', '=', $this->currentFolderId)
            ->withCount(['ledgers'])
            ->get();
    }

    // セマンティック検索ON前の同義語トグル状態を保存
    private $savedUseSynonymState = null;

    public function mount(SearchRequest $request, $folderId = null, $defineId = null)
    {
        $startedAt = microtime(true);
        $selectedFolderCount = is_countable($this->selectedFolderIds)
            ? count($this->selectedFolderIds)
            : 0;
        $selectedLedgerDefineCount = is_countable($this->selectedLedgerDefineIds)
            ? count($this->selectedLedgerDefineIds)
            : 0;

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

        $this->updateSearchMetadata();
        $this->initSearchContext();

        $this->logPerformance('ledger_index_mount', (microtime(true) - $startedAt) * 1000, [
            'selected_folder_count' => $selectedFolderCount,
            'selected_ledger_define_count' => $selectedLedgerDefineCount,
            'search_present' => ! empty($this->search),
        ]);
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
        $this->tags = $searchContext->tags ?? [];
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
        $startedAt = microtime(true);
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
        $this->updateSearchMetadata();
        $this->dispatch('currentFolderChangedByMain', newFolderId: $this->currentFolderId, newSelectedFolderIds: $this->selectedFolderIds);

        $this->logPerformance('ledger_change_current_folder', (microtime(true) - $startedAt) * 1000, [
            'new_folder_id' => $newFolderId,
            'selected_folder_count' => is_countable($this->selectedFolderIds)
                ? count($this->selectedFolderIds)
                : 0,
            'selected_ledger_define_count' => is_countable($this->selectedLedgerDefineIds)
                ? count($this->selectedLedgerDefineIds)
                : 0,
        ]);
    }

    #[On('focusLedgerDefineRequested')]
    public function focusLedgerDefine($defineId)
    {
        $startedAt = microtime(true);
        $this->selectedLedgerDefineIds = [$defineId];
        $this->updateSearchMetadata();

        $this->logPerformance(
            'ledger_focus_define',
            (microtime(true) - $startedAt) * 1000,
            [
                'define_id' => $defineId,
                'selected_ledger_define_count' => 1,
            ]
        );
    }

    #[On('folderIdToggled')]
    public function toggleFolderId($folderId)
    {
        $startedAt = microtime(true);
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

        $this->logPerformance(
            'ledger_toggle_folder',
            (microtime(true) - $startedAt) * 1000,
            [
                'folder_id' => $folderId,
                'selected_folder_count' => is_countable($this->selectedFolderIds)
                    ? count($this->selectedFolderIds)
                    : 0,
                'selected_ledger_define_count' => is_countable($this->selectedLedgerDefineIds)
                    ? count($this->selectedLedgerDefineIds)
                    : 0,
            ]
        );
    }

    #[On('ledgerDefineIdToggled')]
    public function toggleLedgerDefineId($ledgerDefineId)
    {
        $startedAt = microtime(true);
        if (in_array($ledgerDefineId, $this->selectedLedgerDefineIds)) {
            $this->selectedLedgerDefineIds = array_values(array_diff($this->selectedLedgerDefineIds, [$ledgerDefineId]));
        } else {
            $this->selectedLedgerDefineIds[] = $ledgerDefineId;
        }

        sort($this->selectedLedgerDefineIds);

        $this->updateSearchMetadata();

        $this->logPerformance(
            'ledger_toggle_define',
            (microtime(true) - $startedAt) * 1000,
            [
                'define_id' => $ledgerDefineId,
                'selected_ledger_define_count' => count($this->selectedLedgerDefineIds),
            ]
        );
    }

    public function updateRecordCount($total)
    {
        $this->totalRecords = $total;
        $this->totalRecordsLoaded = true;
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

    #[On('confidentialitySectionChanged')]
    public function updateActiveConfidentiality(int $ledgerDefineId): void
    {
        $this->activeLedgerDefineId = $ledgerDefineId;
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

    protected function getPerformanceContext(): array
    {
        $selectedFolderCount = is_countable($this->selectedFolderIds) ? count($this->selectedFolderIds) : 0;
        $selectedLedgerDefineCount = is_countable($this->selectedLedgerDefineIds) ? count($this->selectedLedgerDefineIds) : 0;

        return [
            'tenant_id' => $this->currentTenantId ?? tenant()?->id,
            'current_folder_id' => $this->currentFolderId,
            'display_level' => $this->displayLevel,
            'per_page' => $this->perPage,
            'use_semantic_search' => $this->useSemanticSearch,
            'selected_folder_count' => $selectedFolderCount,
            'selected_ledger_define_count' => $selectedLedgerDefineCount,
            'has_workflow_enabled' => $this->hasWorkflowEnabled,
        ];
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

        $confidentiality = null;
        $canEditConfidentiality = false;

        if ($this->activeLedgerDefineId) {
            $ledgerDefine = LedgerDefine::find($this->activeLedgerDefineId);
            if ($ledgerDefine) {
                $confidentiality = ConfidentialityLevelService::getEffectiveLevel($ledgerDefine);
                $canEditConfidentiality = auth()->user()->can('update', $ledgerDefine);
            }
        }

        if (! $confidentiality && $this->currentFolder) {
            $confidentiality = ConfidentialityLevelService::getEffectiveLevel($this->currentFolder);
            $canEditConfidentiality = auth()->user()->can('update', $this->currentFolder);
        }

        return view('livewire.ledger.index-manager', [
            'keywords' => $this->keywords ?? [],
            'tags' => $this->tags ?? [],
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
            'confidentiality' => $confidentiality,
            'canEditConfidentiality' => $canEditConfidentiality,
        ])->layout('layouts.appWithDrawer', ['title' => __('ledger.records_title')]);
    }
}
