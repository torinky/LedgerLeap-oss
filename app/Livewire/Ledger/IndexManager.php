<?php

namespace App\Livewire\Ledger;

use App\Helpers\SearchHelper; // 追加
use App\Http\Requests\Ledger\SearchRequest;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasSortingLabels;
use App\Livewire\Traits\InitializesTenantContext;
use App\Livewire\Traits\LogPerformance;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Services\ConfidentialityLevelService;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\Ledger\SearchHistoryService; // 追加
use App\Services\Ledger\SearchKeywordService;
use App\Services\PermissionService;
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

    public int $recordsTableMountKey = 0;

    public $highlights = [];

    public bool $showSearchSuggestions = false;

    public array $recentSearches = [];

    public array $popularKeywords = [];

    public array $querySuggestions = [];

    private ?SearchContext $searchContext = null;

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

        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = $this->loadQuerySuggestions();

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
        $this->searchContext = new SearchContext($synonymService);

        $this->searchContext->setSearch($this->search);
        $this->searchContext->setFilter($this->filter);

        $this->keywords = $this->searchContext->keywords ?? [];
        $this->tags = $this->searchContext->tags ?? [];
        $this->highlights = $this->searchContext->highlights ?? [];
        $this->synonyms = $this->searchContext->synonyms ?? [];
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

    /**
     * クリアボタン押下時。search を空にし、RecordsTable の再描画のみを行う。
     */
    public function clearSearch(): void
    {
        $this->search = '';
        $this->initSearchContext();
        $this->recordsTableMountKey++;
        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = [];
    }

    /**
     * サジェスト更新用の軽量メソッド。台帳検索は走らせず、
     * サジェスト候補・人気キーワード・最近の検索のみを更新します。
     * Alpine の 250ms idle タイマから呼ばれます。
     *
     * $this->search は更新しない (RecordsTable の再検索を防ぐため)。
     */
    public function updateSuggestions(string $value): void
    {
        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = $this->loadQuerySuggestionsFor($value);
    }

    /**
     * Enter キー確定時の検索実行メソッド。台帳検索を含む全処理を実行します。
     * Alpine の sendSearchRequest() から呼ばれます。
     */
    public function executeSearch(string $value): void
    {
        $this->search = SearchHelper::normalizeQuery($value);
        $this->recordsTableMountKey++;
        $this->initSearchContext();
        $this->recordSearchHistory();
        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = $this->loadQuerySuggestions();
    }

    public function updatedSearch()
    {
        Log::info('updatedSearch called', ['search' => $this->search]);
        $this->search = SearchHelper::normalizeQuery((string) $this->search);
        $this->initSearchContext();
        $this->recordSearchHistory();
        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = $this->loadQuerySuggestions();
    }

    /**
     * 検索入力の文字種をサーバ側で正規化する (SearchHelper::normalizeQuery への薄いラッパー)。
     * 末尾/先頭スペースは保持する (単語区切り文字を壊さないため)。
     */
    protected function normalizeSearchInput(string $value): string
    {
        return SearchHelper::normalizeQuery($value);
    }

    protected function recordSearchHistory(): void
    {
        if (! auth()->check()) {
            return;
        }

        $hasMeaningfulSearch = ! empty($this->search)
            || ! empty($this->filterStatus)
            || ! empty($this->filter);

        if (! $hasMeaningfulSearch) {
            return;
        }

        $conditions = [
            'q' => $this->search,
            'sort' => $this->orderBy,
            'dir' => $this->orderAsc ? 'asc' : 'desc',
            'status' => $this->filterStatus,
            'filter' => $this->filter,
            'l' => $this->selectedLedgerDefineIds,
            'f' => $this->selectedFolderIds,
            'cf' => $this->currentFolderId,
            'dl' => $this->displayLevel,
            'pp' => $this->perPage,
            'sem' => $this->useSemanticSearch,
            'syn' => $this->useSynonym,
            'tt' => $this->useTechnicalTerm,
        ];

        app(SearchHistoryService::class)->record(
            conditions: $conditions,
            resultCount: (int) ($this->totalRecords ?? 0),
            searchTrace: $this->searchContext?->getTrace() ?? [],
        );
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
        $this->recordsTableMountKey++;
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
     * 子 RecordsTable から dispatch される検索結果件数を受け取り、
     * 検索履歴 (ActivityLog) 記録時の result_count に使用する。
     *
     * Sprint 2 で search-blade.php 内の recent バッジが常に 0 を表示していた
     * のは、このリスナーが欠落していたため totalRecords が初期値 0 のまま
     * 固定されていたのが原因。
     */
    #[On('ledger-records-count-updated')]
    public function receiveRecordsCount(int $total): void
    {
        $this->updateRecordCount($total);
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

    public function toggleSearchSuggestions(): void
    {
        $this->showSearchSuggestions = ! $this->showSearchSuggestions;
    }

    public function applySearch(array $conditions): void
    {
        if (! app(SearchHistoryService::class)->canRestore($conditions)) {
            $this->error(__('ledger.search_suggest.cannot_restore'));

            return;
        }

        // 履歴からの復元は確定値とみなすので trim する
        $this->search = SearchHelper::trimSearch(
            SearchHelper::normalizeQuery($conditions['q'] ?? '')
        );
        $this->orderBy = $conditions['sort'] ?? 'composite_score';
        $this->orderAsc = ($conditions['dir'] ?? 'desc') === 'asc';
        $this->filterStatus = $conditions['status'] ?? '';
        $this->filter = $conditions['filter'] ?? [];
        $this->selectedLedgerDefineIds = $conditions['l'] ?? [];
        $this->selectedFolderIds = $conditions['f'] ?? [];
        $this->currentFolderId = $conditions['cf'] ?? null;
        $this->displayLevel = (int) ($conditions['dl'] ?? 1);
        $this->perPage = $conditions['pp'] ?? 100;
        $this->useSemanticSearch = (bool) ($conditions['sem'] ?? false);
        $this->useSynonym = $conditions['syn'] ?? true;
        $this->useTechnicalTerm = $conditions['tt'] ?? true;

        $this->updateSearchMetadata();
        $this->initSearchContext();

        $this->showSearchSuggestions = false;
        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = $this->loadQuerySuggestions();
    }

    public function applyKeywordSearch(string $keyword): void
    {
        // サジェスト/人気キーワードからの選択は確定値として扱う (trim する)
        $this->search = SearchHelper::trimSearch($this->normalizeSearchInput($keyword));
        $this->recordsTableMountKey++;
        $this->initSearchContext();
        $this->recordSearchHistory();
        $this->showSearchSuggestions = false;
        $this->recentSearches = $this->loadRecentSearches();
        $this->popularKeywords = $this->loadPopularKeywords();
        $this->querySuggestions = $this->loadQuerySuggestions();
    }

    /**
     * クエリ全文サジェスト候補を適用します。applyKeywordSearch() と同じ置換型挙動で
     * 入力欄をクエリ全文で上書きし、検索を実行します。
     */
    public function applyQuerySuggestion(string $queryText): void
    {
        $this->applyKeywordSearch($queryText);
    }

    public function deleteSearchHistory(int $activityId): void
    {
        $deleted = app(SearchHistoryService::class)->delete(auth()->user(), $activityId);
        if ($deleted) {
            $this->recentSearches = $this->loadRecentSearches();
        }
    }

    public function loadRecentSearches(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        return app(SearchHistoryService::class)
            ->getRecent($user, tenant()?->id, 5)
            ->map(fn ($activity) => [
                'id' => $activity->id,
                'label' => $activity->description,
                'conditions' => $activity->properties['conditions'] ?? [],
                'result_count' => $activity->properties['result_count'] ?? 0,
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->toArray();
    }

    public function loadPopularKeywords(): array
    {
        $tenantId = tenant()?->id;
        if (! $tenantId) {
            return [];
        }

        $keywords = app(SearchKeywordService::class)
            ->getPopularKeywords($tenantId, 10);

        // 現在の検索入力に含まれる単語を除外
        $currentWords = $this->currentSearchWords();

        if ($currentWords !== []) {
            $keywords = array_values(array_filter($keywords, function (array $kw) use ($currentWords) {
                return ! in_array(mb_strtolower($kw['keyword']), $currentWords, true);
            }));
        }

        return $keywords;
    }

    /**
     * 入力中のクエリから search_query_words 経由で search_queries を逆引き検索し、
     * マッチ率スコア + log₂(search_count+1) 重みで並べた候補を返します。
     *
     * 空入力時は空配列 (空入力は popularKeywords / recentSearches セクションで
     * カバーされる)。フォールバック判定は Blade 側 (querySuggestions.length) で行う。
     *
     * @return array<int, array{query_text: string, search_count: int, match_rate: float, score: float}>
     */
    public function loadQuerySuggestions(): array
    {
        return $this->loadQuerySuggestionsFor((string) $this->search);
    }

    private function loadQuerySuggestionsFor(string $search): array
    {
        $tenantId = tenant()?->id;

        if (! $tenantId || $search === '') {
            return [];
        }

        $result = app(SearchKeywordService::class)
            ->suggestQueries($search, $tenantId, 8);

        Log::info('loadQuerySuggestions', [
            'search' => $search,
            'tenantId' => $tenantId,
            'count' => count($result),
            'sample' => array_slice(array_column($result, 'query_text'), 0, 3),
        ]);

        return $result;
    }

    private function currentSearchWords(): array
    {
        $search = (string) $this->search;
        if ($search === '') {
            return [];
        }

        return array_values(array_unique(array_filter(
            preg_split('/[\s　]+/u', (string) $this->search),
            static fn (string $w) => $w !== ''
        )));
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

        $folderConfidentiality = null;
        $canEditFolderConfidentiality = false;
        if ($this->currentFolder) {
            $folderConfidentiality = ConfidentialityLevelService::getEffectiveLevel($this->currentFolder);
            $canEditFolderConfidentiality = auth()->user()->can('update', $this->currentFolder);
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
            'confidentiality' => $folderConfidentiality,
            'canEditConfidentiality' => $canEditFolderConfidentiality,
            'recentSearches' => $this->recentSearches,
            'popularKeywords' => $this->popularKeywords,
            'querySuggestions' => $this->querySuggestions,
            'showSearchSuggestions' => $this->showSearchSuggestions,
        ])->layout('layouts.appWithDrawer', ['title' => __('ledger.records_title')]);
    }
}
