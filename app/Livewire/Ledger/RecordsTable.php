<?php

namespace App\Livewire\Ledger;

use App\Enums\AttachedFileStatus;
use App\Helpers\SearchHelper;
use App\Http\Requests\Ledger\SearchRequest;
use App\Jobs\Ledger\ProcessAttachedFile;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasSortingLabels;
use App\Livewire\Traits\InitializesTenantContext;
use App\Livewire\Traits\LogPerformance;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\LedgerDefineStatsService;
use App\Services\Ledger\RecordsGroupingService;
use App\Services\Ledger\SearchContext;
use App\Services\RagSearchService;
use App\Services\SynonymService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

#[Lazy(isolate: false)]
class RecordsTable extends BaseLivewireComponent
{
    use HasSortingLabels, InitializesTenantContext, LogPerformance, Toast, WithPagination;

    #[Reactive]
    public $perPage = 100;

    public string|int|null $currentTenantId = null;

    public bool $hasDispatchedLedgerSectionsRendered = false;

    #[Reactive]
    public $search = '';

    #[Reactive]
    public $orderBy = 'composite_score';

    #[Reactive]
    public $orderAsc = false;

    #[Reactive]
    public $filterStatus = '';

    #[Reactive]
    public $filter = [];

    public $defineId = null;

    public $ledgerDefineRecords;

    public $folderRecords;

    public $currentFolder = null;

    public int $prepareFolderAssetInvocationCount = 0;

    public $breadcrumbs = [];

    #[Reactive]
    public $selectedLedgerDefineIds = [];

    #[Reactive]
    public $selectedFolderIds = [];

    #[Reactive]
    public $currentFolderId;

    #[Reactive]
    public int $displayLevel = 1;

    #[Reactive]
    public $tags = [];

    #[Reactive]
    public $keywords = [];

    public $totalRecords;

    public bool $totalRecordsLoaded = false;

    public string $totalRecordsQuerySignature = '';

    #[Reactive]
    public $highlights = [];

    #[Reactive]
    public $synonyms = [];

    #[Reactive]
    public bool $useSemanticSearch = false;

    #[Reactive]
    public $useSynonym = true;

    #[Reactive]
    public $useTechnicalTerm = true;

    // セマンティック検索ON前の同義語トグル状態を保存
    private $savedUseSynonymState = null;

    private SynonymService $synonymService;

    private SearchContext $searchContext;

    private $synonymServiceConfig;

    public bool $showPermissionModal = false;

    public bool $showActivityModal = false;

    public ?string $modalTitle = null;

    public ?int $modalResourceId = null;

    public ?string $modalResourceType = null;

    public bool $hasWorkflowEnabled = false;

    public string $orderByLabel = '';

    #[Reactive]
    public array $defaultSortColumns = []; // 追加: デフォルトソートカラムを保持

    public ?int $selectedFileId = null;

    public ?int $selectedLedgerId = null;

    public ?int $selectedColumnId = null;

    public bool $isFileInspectorOpen = false;

    /**
     * #[Lazy] プレースホルダー
     * フォルダ切替時は IndexManager の応答（~100ms）にこのスケルトンが含まれる。
     * 実コンテンツは別リクエストで非同期レンダリングされる。
     */
    public function placeholder(): View
    {
        return view('livewire.ledger.records-table-placeholder');
    }

    /**
     * コンポーネントが初めてリクエストされた時に実行される初期化処理
     *
     * @return void
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function mount(SynonymServiceConfig $synonymServiceConfig, SearchRequest $request)
    {
        $startedAt = microtime(true);
        $selectedFolderCount = is_countable($this->selectedFolderIds)
            ? count($this->selectedFolderIds)
            : 0;
        $selectedLedgerDefineCount = is_countable($this->selectedLedgerDefineIds)
            ? count($this->selectedLedgerDefineIds)
            : 0;

        if (session()->has('success')) {
            $this->success(session('success'));
        }

        $this->currentTenantId = tenant()?->id
            ?? $this->tenantId  // #[Lazy] ロード時: boot() が $this->tenantId からテナント初期化済み
            ?? null;

        // 状態初期化の多くは IndexManager (親) に移行したため、
        // RecordsTable では計算が必要なプロパティの初期化のみを行う。

        $this->synonymServiceConfig = $synonymServiceConfig;

        // フォルダーアセットを準備
        $this->prepareFolderAsset();

        $this->hasWorkflowEnabled = $this->ledgerDefineRecords->contains('workflow_enabled', true);

        // 初期orderByLabelの設定
        $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);

        $this->logPerformance('ledger_records_mount', (microtime(true) - $startedAt) * 1000, [
            'current_folder_id' => $this->currentFolderId,
            'selected_folder_count' => $selectedFolderCount,
            'selected_ledger_define_count' => $selectedLedgerDefineCount,
            'has_workflow_enabled' => $this->hasWorkflowEnabled,
        ]);
    }

    #[On('file-inspector-selection-changed')]
    public function syncFileInspectorSelection(
        ?int $selectedFileId = null,
        ?int $selectedColumnId = null,
        bool $isOpen = false,
    ): void {
        $this->selectedFileId = $selectedFileId;
        $this->selectedColumnId = $selectedColumnId;
        $this->isFileInspectorOpen = $isOpen;

        if ($selectedFileId === null) {
            $this->selectedLedgerId = null;

            $this->dispatch(
                'file-inspector-selection-applied',
                selectedFileId: null,
                selectedLedgerId: null,
                selectedColumnId: null,
                isOpen: false,
            );

            return;
        }

        $attachment = AttachedFile::find($selectedFileId);

        $this->selectedLedgerId = $attachment?->ledger_id;

        if ($this->selectedColumnId === null) {
            $this->selectedColumnId = $attachment?->column_id;
        }

        $this->dispatch(
            'file-inspector-selection-applied',
            selectedFileId: $this->selectedFileId,
            selectedLedgerId: $this->selectedLedgerId,
            selectedColumnId: $this->selectedColumnId,
            isOpen: $this->isFileInspectorOpen,
        );
    }

    /**
     * 検索コンテキストを初期化
     *
     * 親から Reactive プロパティとして受け取るため、ここではインスタンスの作成のみ行い、
     * 計算済みの値をセットする。重い再計算（類義語展開など）は行わない。
     */
    protected function initSearchContext()
    {
        $synonymService = new SynonymService($this->synonymServiceConfig);
        $this->searchContext = new SearchContext($synonymService);

        // 親から受け取った状態をそのまま利用し、タグだけを検索コンテキストに注入する。
        $this->searchContext->keywords = $this->keywords ?? [];
        $this->searchContext->tags = $this->tags ?? [];
        $this->searchContext->highlights = $this->highlights ?? [];
        $this->searchContext->synonyms = $this->synonyms ?? [];
        $this->searchContext->filter = $this->filter ?? [];
    }

    protected function getPerformanceContext(): array
    {
        $selectedFolderCount = is_countable($this->selectedFolderIds)
            ? count($this->selectedFolderIds)
            : 0;
        $selectedLedgerDefineCount = is_countable($this->selectedLedgerDefineIds)
            ? count($this->selectedLedgerDefineIds)
            : 0;

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

    protected function clearTotalRecordsCache(): void
    {
        $this->totalRecordsLoaded = false;
        $this->totalRecordsQuerySignature = '';
    }

    protected function buildTotalRecordsQuerySignature(array $searchTargetLedgerDefineIds): string
    {
        $payload = [
            'search' => $this->search,
            'search_target_ledger_define_ids' => array_values(array_map(
                'intval',
                array_unique($searchTargetLedgerDefineIds)
            )),
            'keywords' => $this->searchContext?->keywords ?? [],
            'highlights' => $this->searchContext?->highlights ?? [],
            'synonyms' => $this->searchContext?->synonyms ?? [],
            'filter' => $this->searchContext?->filter ?? [],
            'filter_status' => $this->filterStatus,
            'use_semantic_search' => $this->useSemanticSearch,
            'use_synonym' => $this->useSynonym,
            'use_technical_term' => $this->useTechnicalTerm,
            'selected_ledger_define_ids' => array_values(array_map(
                'intval',
                $this->selectedLedgerDefineIds ?? []
            )),
            'selected_folder_ids' => array_values(array_map(
                'intval',
                $this->selectedFolderIds ?? []
            )),
        ];

        return sha1(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function dispatchTotalRecordsChanged(int $totalRecords): void
    {
        $this->dispatch('ledger-records-count-updated', total: $totalRecords);
    }

    /**
     * orderByが変更されたときにorderByLabelを更新するライフサイクルフック
     */
    public function updatedOrderBy($value)
    {
        $this->orderByLabel = $this->getStandardSortLabel($value);
    }

    /**
     * 検索語が変更されたときに実行されるライフサイクルフック
     */
    public function updatedSearch($value)
    {
        $this->resetPage();
    }

    /**
     * フィルターが変更されたときに実行されるライフサイクルフック
     */
    public function updatedFilter($value)
    {
        $this->resetPage();
    }

    /**
     * 表示フォルダが変更されたときに実行されるライフサイクルフック
     */
    public function updatedCurrentFolderId($value)
    {
        $this->resetPage();
        $this->prepareFolderAsset();
    }

    /**
     * 選択フォルダが変更されたときに実行されるライフサイクルフック
     */
    public function updatedSelectedFolderIds($value)
    {
        $this->resetPage();
    }

    /**
     * 選択台帳が変更されたときに実行されるライフサイクルフック
     */
    public function updatedSelectedLedgerDefineIds($value)
    {
        $this->resetPage();
    }

    /**
     * ソート順が変更された際に SearchContext を再初期化
     */
    public function updatedOrderAsc($value)
    {
        // ページのリセットのみ
        $this->resetPage();
    }

    /**
     * セマンティック検索トグルが変更されたときに実行されるライフサイクルフック
     */
    public function updatedUseSemanticSearch($value)
    {
        $this->resetPage();
    }

    /**
     * 同義語トグルが変更された際に SearchContext を再初期化
     */
    public function updatedUseSynonym($value)
    {
        $this->resetPage();
    }

    /**
     * 専門用語トグルが変更された際に SearchContext を再初期化
     */
    public function updatedUseTechnicalTerm($value)
    {
        $this->resetPage();
    }

    /**
     * コレクションに対してソートを適用する
     *
     * @param  Collection  $collection
     * @param  string  $sortBy
     * @param  bool  $orderAsc
     * @return Collection
     */
    private function applySorting($collection, $sortBy, $orderAsc)
    {
        return $collection->sortBy($sortBy, SORT_REGULAR, ! $orderAsc);
    }

    /**
     * ページネーションのリセット（親側での変更を検知してリセットが必要な場合に使用する hooks があれば）
     */

    /**
     * 表示レベルを設定する
     */
    public function setDisplayLevel(int $level): void
    {
        $this->dispatch('displayLevelRequested', level: $level);
    }

    /**
     * コンポーネントの表示を更新する
     */
    #[On('ledgerStored')]
    #[On('permissions-changed')]
    public function refresh(): void
    {
        $this->clearTotalRecordsCache();
        $this->prepareFolderAsset();
    }

    #[On('openPermissionModalRequested')]
    public function handleOpenPermissionModal(string $resourceType, int $resourceId, string $title): void
    {
        $this->openPermissionModal($resourceType, $resourceId, $title);
    }

    #[On('openActivityModalRequested')]
    public function handleOpenActivityModal(string $resourceType, int $resourceId, string $title): void
    {
        $this->openActivityModal($resourceType, $resourceId, $title);
    }

    public function render()
    {
        $startedAt = microtime(true);
        $tenantId = tenancy()->tenant?->id ?? 'central';
        $dbName = \DB::connection()->getDatabaseName();
        $ledgerRecords = collect();
        $ledgerDefineRecords = collect();
        $ledgerSelectColumns = [
            'id',
            'ledger_define_id',
            'content',
            'content_attached',
            'updated_at',
            'status',
            'composite_score',
            'default_sort_value',
        ];
        Log::info('[MCP Debug] RecordsTable.render START', [
            'tenantId' => $tenantId,
            'dbName' => $dbName,
            'search' => $this->search,
            'selectedLedgerDefineIds' => $this->selectedLedgerDefineIds,
        ]);

        $this->initSearchContext();

        // Export コンポーネントに検索条件をブラウザイベントとして通知する。
        // PHP の #[On] ではなく Alpine.js の x-on:refresh-children.window で受け取ることで
        // サーバーラウンドトリップを発生させず、セッションロック起因の遅延を防ぐ。
        $this->dispatch('refresh-children', data: [
            'keywords' => $this->keywords,
            'filter' => $this->filter,
        ]);

        $searchTargetLedgerDefineIdsStartedAt = microtime(true);
        $searchTargetLedgerDefineIdsCount = 0;
        $searchTargetLedgerDefineIdsMode = 'unscoped';
        $displayLedgerDefinesDurationMs = 0.0;
        $displayLedgerDefinesQueryDurationMs = 0.0;
        $displayLedgerDefinesLoadDurationMs = 0.0;
        $breadcrumbsPreparedDurationMs = 0.0;
        $ledgerRecordsQueryPrepDurationMs = 0.0;
        $relatedLedgerDefineIdsDurationMs = 0.0;
        $missingDefineFetchDurationMs = 0.0;
        $ledgerRecordsQueryCountDurationMs = 0.0;
        $ledgerRecordsQueryCountCacheHit = false;
        $ledgerRecordsQueryPaginateDurationMs = 0.0;
        $ledgerRecordsDefineLoadDurationMs = 0.0;
        $pageLedgerDefineCount = 0;

        // グローバル検索かどうかの判定
        $isGlobalSearch = ! empty($this->search)
            && empty($this->selectedLedgerDefineIds)
            && empty($this->selectedFolderIds);

        if ($isGlobalSearch) {
            // グローバル検索の場合、すべての台帳定義IDを対象にする
            $searchTargetLedgerDefineIdsMode = 'global';
            $searchTargetLedgerDefineIds = LedgerDefine::query()
                ->searchTags($this->searchContext->tags)
                ->pluck('id')
                ->toArray();
        } else {
            // 通常の場合、選択された台帳定義のみを対象にする
            $searchTargetLedgerDefineIdsMode = ! empty($this->selectedLedgerDefineIds)
                ? 'selected'
                : 'unscoped';
            $searchTargetLedgerDefineIds = LedgerDefine::whereIn('id', $this->selectedLedgerDefineIds)
                ->searchTags($this->searchContext->tags)
                ->pluck('id')
                ->toArray();
        }
        $searchTargetLedgerDefineIdsCount = count($searchTargetLedgerDefineIds);
        $searchTargetLedgerDefineIdsDurationMs = (microtime(true) - $searchTargetLedgerDefineIdsStartedAt) * 1000;

        // 表示対象の台帳に紐づく仕訳データを取得
        $ledgerRecordsQueryStartedAt = microtime(true);
        $ledgerRecordsDefineLoadDurationMs = 0.0;
        $ragEnabled = config('rag.enabled', false);

        if ($ragEnabled && $this->useSemanticSearch && ! empty($this->search)) {
            // ============================================
            // セマンティック検索モード
            // ============================================

            // Step 1: 検索クエリの準備
            // セマンティック検索では元のキーワードのみを使用（同義語展開はしない）
            // 理由: ベクトル検索は意味的な類似性を計算するため、
            //       同義語を追加するとクエリの意味がぼやけて精度が落ちる
            $searchQuery = ! empty($this->searchContext->keywords)
                ? implode(' ', $this->searchContext->keywords)
                : $this->search;

            // Step 2: RAGで検索（スコア情報付きで全件取得）
            $ragResults = app(RagSearchService::class)->searchLedgers(
                query: $searchQuery,
                limit: 1000, // 十分な件数を取得（後でソート・ページネーション）
                filters: array_merge($this->filter, [
                    'user' => auth()->user(),
                    'ledger_define_ids' => $searchTargetLedgerDefineIds,
                ])
            );

            if (empty($ragResults)) {
                // 検索結果がない場合
                $this->totalRecords = 0;
                $this->totalRecordsLoaded = true;
                $this->totalRecordsQuerySignature = $this->buildTotalRecordsQuerySignature(
                    $searchTargetLedgerDefineIds
                );
                $this->dispatchTotalRecordsChanged($this->totalRecords);
                $ledgerRecords = new LengthAwarePaginator(
                    [],
                    0,
                    $this->perPage,
                    Paginator::resolveCurrentPage()
                );
                $ledgerDefineRecords = collect()->keyBy('id');
            } else {
                // Step 2: Ledgerモデルを取得
                $ledgerIds = array_column($ragResults, 'ledger_id');
                $scoreMap = collect($ragResults)->pluck('max_score', 'ledger_id');

                $ledgersCollection = Ledger::whereIn('id', $ledgerIds)
                    ->select($ledgerSelectColumns)
                    ->whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
                    ->when(! empty($this->filterStatus), function ($query) {
                        return $query->where('status', $this->filterStatus);
                    })
                    ->get();

                $ledgerRecordsDefineLoadStartedAt = microtime(true);
                $ledgersCollection->load(['define:id,folder_id,workflow_enabled,column_define']);
                $ledgerRecordsDefineLoadDurationMs = (microtime(true) - $ledgerRecordsDefineLoadStartedAt) * 1000;

                // Step 3: スコアを動的属性として付与
                $ledgersCollection->each(function ($ledger) use ($scoreMap) {
                    $ledger->semantic_score = $scoreMap[$ledger->id] ?? 0;
                });

                // Step 4: 並び順を適用
                // セマンティック検索時、composite_scoreが選択されている場合はsemantic_scoreを使用
                $sortBy = ($this->orderBy === 'composite_score') ? 'semantic_score' : $this->orderBy;

                $sortedLedgers = $this->applySorting($ledgersCollection, $sortBy, $this->orderAsc);

                // Step 5: ページネーション
                $this->totalRecords = $sortedLedgers->count();
                $this->totalRecordsLoaded = true;
                $this->totalRecordsQuerySignature = $this->buildTotalRecordsQuerySignature(
                    $searchTargetLedgerDefineIds
                );
                $this->dispatchTotalRecordsChanged($this->totalRecords);
                $currentPage = Paginator::resolveCurrentPage();
                $currentPageItems = $sortedLedgers->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

                $ledgerRecords = new LengthAwarePaginator(
                    $currentPageItems,
                    $this->totalRecords,
                    $this->perPage,
                    $currentPage
                );

                // 台帳定義情報を取得
                $pageLedgerDefineCount = count($ledgerRecords->pluck('ledger_define_id')->unique()->toArray());
                $ledgerDefineRecords = LedgerDefine::whereIn('id', $ledgerRecords->pluck('ledger_define_id')->unique()->toArray())
                    ->with(['folder.ancestors', 'tags'])
                    ->get()
                    ->keyBy('id');
            }
        } else {
            // ============================================
            // 通常検索モード
            // ============================================
            $ledgerRecordsQueryPrepStartedAt = microtime(true);
            $ledgerRecordsQuery = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
                ->select($ledgerSelectColumns)
                ->searchContext($this->searchContext)
                ->contentsFilter($this->filter)
                ->when(! empty($this->filterStatus), function ($query) {
                    return $query->where('status', $this->filterStatus);
                })
                ->orderBy('ledger_define_id', 'asc')
                ->when($this->orderBy === 'default', function ($query) {
                    // 新設した denormalized カラムを使用して高速にソート
                    return $query->orderBy('default_sort_value', $this->orderAsc ? 'asc' : 'desc');
                })
                ->when($this->orderBy === 'composite_score', function ($query) {
                    return $query->orderByRaw('composite_score = 0, composite_score '.
                        ($this->orderAsc ? 'ASC' : 'DESC'));
                }, function ($query) {
                    // デフォルトソートが設定されていない、かつ orderBy が default ではない場合
                    if ($this->orderBy !== 'default') {
                        return $query->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
                    }
                });

            $ledgerRecordsQueryPrepDurationMs = (microtime(true) - $ledgerRecordsQueryPrepStartedAt) * 1000;

            $totalRecordsQuerySignature = $this->buildTotalRecordsQuerySignature(
                $searchTargetLedgerDefineIds
            );
            $ledgerRecordsQueryCountCacheHit =
                $this->totalRecordsLoaded
                && $this->totalRecordsQuerySignature === $totalRecordsQuerySignature;

            if ($ledgerRecordsQueryCountCacheHit) {
                $newTotal = (int) $this->totalRecords;
            } else {
                $ledgerRecordsQueryCountStartedAt = microtime(true);
                $newTotal = $ledgerRecordsQuery->count();
                $ledgerRecordsQueryCountDurationMs =
                    (microtime(true) - $ledgerRecordsQueryCountStartedAt) * 1000;
                $this->totalRecords = $newTotal;
                $this->totalRecordsLoaded = true;
                $this->totalRecordsQuerySignature = $totalRecordsQuerySignature;
                $this->dispatchTotalRecordsChanged($this->totalRecords);
            }

            // ページネーション実行
            $ledgerRecordsQueryPaginateStartedAt = microtime(true);
            $ledgerRecords = $ledgerRecordsQuery->simplePaginate($this->perPage);
            $ledgerRecordsQueryPaginateDurationMs = (microtime(true) - $ledgerRecordsQueryPaginateStartedAt) * 1000;

            $ledgerRecordsDefineLoadStartedAt = microtime(true);
            $ledgerRecords->getCollection()->load(['define:id,folder_id,workflow_enabled,column_define']);
            $ledgerRecordsDefineLoadDurationMs = (microtime(true) - $ledgerRecordsDefineLoadStartedAt) * 1000;

            $pageLedgerDefineIds = $ledgerRecords->pluck('ledger_define_id')->unique()->toArray();
            $pageLedgerDefineCount = count($pageLedgerDefineIds);
            $ledgerDefineRecords = LedgerDefine::whereIn('id', $pageLedgerDefineIds)
                ->with(['folder.ancestors', 'tags'])
                ->get()
                ->keyBy('id');
            //            Log::info('RecordsTable render: ledgerRecords after simplePaginate', ['ledgerRecords' => $ledgerRecords->toArray()]);
        }
        $ledgerRecordsQueryDurationMs = (microtime(true) - $ledgerRecordsQueryStartedAt) * 1000;

        $breadcrumbsPerLedgerDefine = [];
        $breadcrumbsPreparedStartedAt = microtime(true);
        foreach ($ledgerDefineRecords as $ledgerDefineRecord) {
            if ($ledgerDefineRecord->folder) {
                $ancestors = $ledgerDefineRecord->folder->ancestors;
                $breadcrumbsPerLedgerDefine[$ledgerDefineRecord->id] = $ancestors->push($ledgerDefineRecord->folder)->all();
            } else {
                $breadcrumbsPerLedgerDefine[$ledgerDefineRecord->id] = [];
            }
        }
        $breadcrumbsPreparedDurationMs = (microtime(true) - $breadcrumbsPreparedStartedAt) * 1000;

        // 検索ヒットのマーキング
        $searchHitMarkDurationMs = 0.0;
        $ledgerRecords->getCollection()->transform(function ($ledger) use (
            &$searchHitMarkDurationMs
        ) {
            if (empty($ledger->content_attached) || empty($this->search)) {
                return $ledger;
            }

            $searchHitMarkStartedAt = microtime(true);
            $contentAttached = $ledger->content_attached;
            $hits = $this->searchContext->highlights;
            foreach ($contentAttached as $key => $attached) {
                if (empty($attached)) {
                    continue;
                }
                foreach ($attached as $hashedfilename => $metaData) {
                    if (SearchHelper::isFileDataHit($metaData, $hits)) {
                        if (is_array($metaData)) {
                            $contentAttached[$key][$hashedfilename]['hit'] = true;
                        } else {
                            $contentAttached[$key][$hashedfilename]->hit = true;
                        }
                    }
                }
            }
            $searchHitMarkDurationMs += (microtime(true) - $searchHitMarkStartedAt) * 1000;
            $ledger->content_attached = $contentAttached;

            return $ledger;
        });
        $attachmentsFetchDurationMs = 0.0;

        $currentFolder = $this->currentFolder;

        // Filter column_define for each ledgerDefine based on displayLevel
        $filteredColumnDefinesStartedAt = microtime(true);
        $filteredColumnDefines = $ledgerDefineRecords->map(function ($ledgerDefine) {
            return collect($ledgerDefine->column_define)
                ->filter(function ($column) {
                    $columnDisplayLevel = $column->display_level ?? 3;

                    return $columnDisplayLevel <= $this->displayLevel;
                })
                ->sortBy('order');
        });
        $filteredColumnDefinesDurationMs = (microtime(true) - $filteredColumnDefinesStartedAt) * 1000;

        // 統計計算とグルーピングをサービスに委譲（キャッシュ付き）
        $groupingStartedAt = microtime(true);
        $groupingResult = app(RecordsGroupingService::class)
            ->groupAndComputeStats($ledgerRecords, ! empty($this->search), $this->currentTenantId);
        $ledgerRecordsGroupByDefineIds = $groupingResult['groups'];
        $scoreStatsByDefineId = collect($groupingResult['stats']);
        $groupingTiming = $groupingResult['timing'];
        $groupingDurationMs = (microtime(true) - $groupingStartedAt) * 1000;
        $scoreStatsDurationMs = $groupingTiming['stats_compute_ms'] ?? 0;

        // 台帳定義全体統計の計算（SQL集計・キャッシュ付き）
        $overallStatsStartedAt = microtime(true);
        $overallStatsByDefineId = collect(
            app(LedgerDefineStatsService::class)
                ->computeOverallStats(
                    $searchTargetLedgerDefineIds ?? [],
                    $this->currentTenantId,
                    auth()->user()
                )
        );
        $overallStatsDurationMs = (microtime(true) - $overallStatsStartedAt) * 1000;

        $viewPrepareStartedAt = microtime(true);

        $this->logPerformance('ledger_records_render', (microtime(true) - $startedAt) * 1000, [
            'record_count' => method_exists($ledgerRecords, 'count') ? $ledgerRecords->count() : 0,
            'group_count' => $ledgerRecordsGroupByDefineIds->count(),
            'attachment_count' => 0,
            'search_present' => ! empty($this->search),
            'semantic_search' => $this->useSemanticSearch,
            'display_ledger_defines_ms' => round($displayLedgerDefinesDurationMs, 2),
            'display_ledger_defines_query_ms' => round($displayLedgerDefinesQueryDurationMs, 2),
            'display_ledger_defines_load_ms' => round($displayLedgerDefinesLoadDurationMs, 2),
            'breadcrumbs_prepared_ms' => round($breadcrumbsPreparedDurationMs, 2),
            'ledger_records_query_ms' => round($ledgerRecordsQueryDurationMs, 2),
            'attachments_fetch_ms' => round($attachmentsFetchDurationMs, 2),
            'search_hit_mark_ms' => round($searchHitMarkDurationMs, 2),
            'filtered_column_defines_ms' => round($filteredColumnDefinesDurationMs, 2),
            'score_stats_ms' => round($scoreStatsDurationMs, 2),
            'grouping_ms' => round($groupingDurationMs, 2),
            'overall_stats_ms' => round($overallStatsDurationMs, 2),
            'view_prepare_ms' => round((microtime(true) - $viewPrepareStartedAt) * 1000, 2),
            'ledger_records_query_prep_ms' => round($ledgerRecordsQueryPrepDurationMs ?? 0.0, 2),
            'related_ledger_define_ids_ms' => round($relatedLedgerDefineIdsDurationMs ?? 0.0, 2),
            'missing_define_fetch_ms' => round($missingDefineFetchDurationMs ?? 0.0, 2),
            'ledger_records_query_count_ms' => round($ledgerRecordsQueryCountDurationMs ?? 0.0, 2),
            'ledger_records_query_count_cache_hit' => $ledgerRecordsQueryCountCacheHit,
            'grouping_cache_hit' => $groupingTiming['cache_hit'] ?? false,
            'ledger_records_query_paginate_ms' => round($ledgerRecordsQueryPaginateDurationMs ?? 0.0, 2),
            'ledger_records_define_load_ms' => round($ledgerRecordsDefineLoadDurationMs, 2),
            'search_target_ledger_define_ids_ms' => round($searchTargetLedgerDefineIdsDurationMs, 2),
            'search_target_ledger_define_ids_count' => $searchTargetLedgerDefineIdsCount,
            'search_target_ledger_define_ids_mode' => $searchTargetLedgerDefineIdsMode,
            'page_ledger_define_count' => $pageLedgerDefineCount ?? 0,
        ]);

        if (! $this->hasDispatchedLedgerSectionsRendered) {
            $this->dispatch('ledger-sections-rendered');
            $this->hasDispatchedLedgerSectionsRendered = true;
        }

        return view('livewire.ledger.records-table', [
            'ledgerRecords' => $ledgerRecords,
            'ledgerRecordsGroupByDefineIds' => $ledgerRecordsGroupByDefineIds,
            'ledgerDefineRecordsKeyById' => $ledgerDefineRecords,
            'displayLevel' => $this->displayLevel,
            'currentFolder' => $currentFolder,
            'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
            'filteredColumnDefines' => $filteredColumnDefines,
            'scoreStatsByDefineId' => $scoreStatsByDefineId,
            'overallStatsByDefineId' => $overallStatsByDefineId,
            'keywords' => $this->keywords,
            'tags' => $this->tags,
            'synonyms' => $this->synonyms,
            'highlights' => $this->highlights,
            'totalRecords' => $this->totalRecords,
        ]);
    }

    #[On('permissions-changed')]
    public function refreshDueToPermissionChange()
    {
        $this->clearTotalRecordsCache();
        // このメソッドが存在し、イベントをリッスンするだけで、
        // Livewireがコンポーネントを再レンダリングし、render()が自動的に呼び出される
    }

    /**
     * 列のソートを行う
     */
    public function sort($columnName, $columnLabel = null)
    {
        $this->dispatch('sortRequested', columnName: $columnName, columnLabel: $columnLabel);
    }

    /**
     * フィルターを更新する (IndexManager側で処理されるため、このメソッドは基本呼ばれない想定だが整理のために残す、または削除)
     * 現在、Viewからは $parent.updateFilterFromChild が呼ばれている。
     */
    // 削除

    /**
     * 現在のフォルダーを変更する
     */
    public function changeCurrentFolder($newFolderId)
    {
        $this->dispatch('currentFolderChangeRequested', newFolderId: $newFolderId);
    }

    /**
     * フォルダの選択状態をトグルする
     */
    public function toggleFolderId($folderId)
    {
        $this->dispatch('folderIdToggled', folderId: $folderId);
    }

    /**
     * 台帳の選択状態をトグルする
     */
    public function toggleLedgerDefineId($ledgerDefineId)
    {
        $this->dispatch('ledgerDefineIdToggled', ledgerDefineId: $ledgerDefineId);
    }

    /**
     * 選択する台帳を1つにする
     */
    public function focusLedgerDefine($defineId)
    {
        $this->dispatch('focusLedgerDefineRequested', defineId: $defineId);
    }

    /**
     * フォルダーアセットを準備する
     */
    public function prepareFolderAsset(): void
    {
        $this->prepareFolderAssetInvocationCount++;

        // 既に準備済みの場合はスキップ（重複実行を防ぐ）
        if (isset($this->currentFolder) && $this->currentFolder && $this->currentFolder->id === $this->currentFolderId) {
            return;
        }

        // currentFolderId が未設定、または別テナントのID/存在しないIDの可能性があるためガードする
        $currentFolder = null;

        if (! empty($this->currentFolderId)) {
            // IndexManager と同様に、子フォルダや台帳定義の件数をカウントするために withCount を追加
            $currentFolder = Folder::with(['ancestors'])->find($this->currentFolderId);
        }

        // それでも見つからなければ、例外にせず空データで返す（UI崩壊防止）
        if (! $currentFolder) {
            $this->breadcrumbs = [];
            $this->folderRecords = collect();
            $this->ledgerDefineRecords = collect();

            return;
        }

        // render() で再検索するのを避けるため、プロパティに保持
        $this->currentFolder = $currentFolder;

        $this->breadcrumbs = $currentFolder->ancestors->all();
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()
            ->withCount(['ledgerDefines']) // 追加
            ->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)
            ->withCount(['ledgers']) // 追加
            ->get();
    }

    /**
     * ページネーションの総ページ数を計算する
     *
     * @return int
     */
    public function lastPage()
    {
        if (empty($this->perPage) || $this->perPage == 0) {
            return 1;
        }

        return ceil(($this->totalRecords ?? 0) / $this->perPage);
    }

    /**
     * ページサイズが変更された際に、現在のページをリセットする
     *
     * @return void
     */
    public function updatingPerPage()
    {
        // perPageを変更した場合、currentPageを最初のページにリセットする
        $this->resetPage();
    }

    public function openPermissionModal(string $resourceType, int $resourceId, string $title): void
    {
        $this->modalResourceType = $resourceType;
        $this->modalResourceId = $resourceId;
        $this->modalTitle = $title.' '.__('ledger.access_and_permissions.title');
        $this->showPermissionModal = true;
    }

    public function openActivityModal(string $resourceType, int $resourceId, string $title): void
    {
        $this->modalResourceType = $resourceType;
        $this->modalResourceId = $resourceId;
        $this->modalTitle = $title.' '.__('ledger.activity.title');
        $this->showActivityModal = true;
    }

    #[On('retryProcessingEvent')]
    public function retryProcessing(int $attachedFileId): void
    {
        $attachedFile = AttachedFile::find($attachedFileId);

        if (! $attachedFile) {
            $this->dispatch('toast', type: 'error', message: __('ledger.messages.file_not_found'));

            return;
        }

        try {
            $attachedFile->update(['status' => AttachedFileStatus::PENDING_INITIAL_PROCESSING->value]);
            ProcessAttachedFile::dispatch($attachedFile);
            $this->dispatch('toast', type: 'success', message: __('ledger.messages.processing_retried'));
        } catch (\Exception $e) {
            $this->dispatch('toast', type: 'error', message: __('ledger.messages.processing_retry_failed', ['error' => $e->getMessage()]));
        }
    }

    /**
     * 自動採番型が純粋な数値のみ（プレフィックスやリビジョンなし）であるか判定
     */
    private function isPurelyNumericAutoNumber(array $column): bool
    {
        if (($column['type'] ?? '') !== 'auto_number') {
            return false;
        }

        $options = $column['options'] ?? [];
        $prefix = $options['prefix'] ?? '';
        $revision = $options['revision'] ?? '';

        return empty($prefix) && empty($revision);
    }
}
