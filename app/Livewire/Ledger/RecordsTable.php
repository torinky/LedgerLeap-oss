<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest;
use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HasSortingLabels;
use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive; // 追加
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RecordsTable extends BaseLivewireComponent
{
    use HasSortingLabels, InitializesTenantContext, Toast, WithPagination;

    #[Reactive]
    public $perPage = 100;

    public string|int|null $currentTenantId = null;

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

    public $breadcrumbs = [];

    #[Reactive]
    public $selectedLedgerDefineIds = [];

    #[Reactive]
    public $selectedFolderIds = [];

    #[Reactive]
    public $currentFolderId;

    #[Reactive]
    public int $displayLevel = 1;

    private $tags = [];

    #[Reactive]
    public $keywords = [];

    public $totalRecords;

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
        if (session()->has('success')) {
            $this->success(session('success'));
        }

        $this->currentTenantId = tenant()?->id;

        // 状態初期化の多くは IndexManager (親) に移行したため、
        // RecordsTable では計算が必要なプロパティの初期化のみを行う。

        $this->synonymServiceConfig = $synonymServiceConfig;

        // フォルダーアセットを準備
        $this->prepareFolderAsset();

        $this->hasWorkflowEnabled = $this->ledgerDefineRecords->contains('workflow_enabled', true);

        // 初期orderByLabelの設定
        $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);
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

        // プロパティ値をセットして検索に備える
        // 注意: setSearch() などを呼ぶと内部で再計算される可能性があるため、
        // 必要なプロパティのみを直接セットするか、再計算を防ぐ構成にする。
        $this->searchContext->keywords = $this->keywords ?? [];
        $this->searchContext->highlights = $this->highlights ?? [];
        $this->searchContext->synonyms = $this->synonyms ?? [];
        $this->searchContext->filter = $this->filter ?? [];
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
     * @param  \Illuminate\Support\Collection  $collection
     * @param  string  $sortBy
     * @param  bool  $orderAsc
     * @return \Illuminate\Support\Collection
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
     *
     * @return Application|Factory|View
     */
    #[On('ledgerStored')]
    #[On('permissions-changed')]
    #[On('recordsUpdated')]
    public function refresh()
    {
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
        $tenantId = tenancy()->tenant?->id ?? 'central';
        $dbName = \DB::connection()->getDatabaseName();
        Log::info('[MCP Debug] RecordsTable.render START', [
            'tenantId' => $tenantId,
            'dbName' => $dbName,
            'search' => $this->search,
            'selectedLedgerDefineIds' => $this->selectedLedgerDefineIds,
        ]);

        $this->initSearchContext();

        // Reactiveプロパティの変更に伴い、フォルダーアセット（パンくず、子フォルダ等）を再取得
        // render で毎回呼ぶのを避けるため、必要な時のみ実行されるように mount と updatedXXX で管理されるべきだが、
        // 現状は安全のためここでの実行を維持（クエリキャッシュがあれば高速）。
        $this->prepareFolderAsset();

        // Exportに検索条件を伝えるためにイベントをトリガ
        $this->dispatch('refreshChildren', data: [
            'keywords' => $this->keywords,
            'filter' => $this->filter,
        ]);

        // グローバル検索かどうかの判定
        $isGlobalSearch = ! empty($this->search) && empty($this->selectedLedgerDefineIds) && empty($this->selectedFolderIds);

        if ($isGlobalSearch) {
            // グローバル検索の場合、すべての台帳定義を対象にする
            $displayLedgerDefines = LedgerDefine::query()
                ->searchTags($this->searchContext->tags)
                ->with(['folder.ancestors.roles', 'folder.roles', 'roles', 'tags'])
                ->get();
            $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];
        } else {
            // 通常の場合、選択された台帳定義のみを対象にする
            $displayLedgerDefines = LedgerDefine::WhereIn('id', $this->selectedLedgerDefineIds)
                ->searchTags($this->searchContext->tags)
                ->with(['folder.ancestors.roles', 'folder.roles', 'roles', 'tags'])
                ->get();
            $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];
        }

        $breadcrumbsPerLedgerDefine = [];
        foreach ($displayLedgerDefines as $displayLedgerDefine) {
            // 台帳ごとのパンくずリストを準備
            if ($displayLedgerDefine->folder) {
                // with('folder.ancestors') により、ここでは追加クエリが発生しないはず
                $ancestors = $displayLedgerDefine->folder->ancestors;
                $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id] = $ancestors->push($displayLedgerDefine->folder)->all();
            } else {
                // フォルダが存在しない場合のフォールバック処理
                $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id] = [];
            }
        }

        // 表示対象の台帳に紐づく仕訳データを取得
        if ($this->useSemanticSearch && ! empty($this->search)) {
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
            $ragResults = app(\App\Services\RagSearchService::class)->searchLedgers(
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
                $ledgerRecords = new \Illuminate\Pagination\LengthAwarePaginator(
                    [],
                    0,
                    $this->perPage,
                    \Illuminate\Pagination\Paginator::resolveCurrentPage()
                );
                $ledgerDefineRecords = collect()->keyBy('id');
            } else {
                // Step 2: Ledgerモデルを取得
                $ledgerIds = array_column($ragResults, 'ledger_id');
                $scoreMap = collect($ragResults)->pluck('max_score', 'ledger_id');

                $ledgersCollection = Ledger::whereIn('id', $ledgerIds)
                    ->whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
                    ->when(! empty($this->filterStatus), function ($query) {
                        return $query->where('status', $this->filterStatus);
                    })
                    ->with(['define'])
                    ->get();

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
                $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
                $currentPageItems = $sortedLedgers->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

                $ledgerRecords = new \Illuminate\Pagination\LengthAwarePaginator(
                    $currentPageItems,
                    $this->totalRecords,
                    $this->perPage,
                    $currentPage
                );

                // 台帳定義情報を取得
                $ledgerDefineRecords = LedgerDefine::whereIn('id', $ledgerRecords->pluck('ledger_define_id')->unique()->toArray())
                    ->with(['folder.ancestors.roles', 'folder.roles', 'tags'])
                    ->get()
                    ->keyBy('id');
            }
        } else {
            // ============================================
            // 通常検索モード
            // ============================================
            $ledgerRecordsQuery = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
                ->searchContext($this->searchContext)
                ->contentsFilter($this->filter)
                ->when(! empty($this->filterStatus), function ($query) {
                    return $query->where('status', $this->filterStatus);
                })
                ->with(['define.folder'])
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

            // 台帳定義とフォルダ情報を先に取得
            // $displayLedgerDefines を再利用して重複クエリを削減
            $relatedLedgerDefineIds = (clone $ledgerRecordsQuery)
                ->reorder()
                ->distinct()
                ->pluck('ledger_define_id')
                ->toArray();

            // 既に取得済みの$displayLedgerDefinesから必要なものを抽出
            // 追加で必要なものがあれば取得
            $displayDefineIds = $displayLedgerDefines->pluck('id')->toArray();
            $missingDefineIds = array_diff($relatedLedgerDefineIds, $displayDefineIds);

            if (! empty($missingDefineIds)) {
                // 不足している台帳定義を追加取得
                $additionalDefines = LedgerDefine::whereIn('id', $missingDefineIds)
                    ->with(['folder.ancestors.roles', 'folder.roles', 'roles', 'tags'])
                    ->get();
                $displayLedgerDefines = $displayLedgerDefines->merge($additionalDefines);
            }

            $ledgerDefineRecords = $displayLedgerDefines->whereIn('id', $relatedLedgerDefineIds)->keyBy('id');

            // 総数を取得
            Log::info('[MCP Debug] RecordsTable.render searchTargetLedgerDefineIds: '.json_encode($searchTargetLedgerDefineIds));
            Log::info('[MCP Debug] RecordsTable.render search: '.$this->search);

            $newTotal = $ledgerRecordsQuery->count();
            Log::info('[MCP Debug] RecordsTable.render count result', [
                'count' => $newTotal,
                'sql' => $ledgerRecordsQuery->toSql(),
                'bindings' => $ledgerRecordsQuery->getBindings(),
            ]);

            if ($this->totalRecords !== $newTotal) {
                $this->totalRecords = $newTotal;
                $this->dispatch('recordsUpdated', total: $this->totalRecords);
            }

            Log::info('[MCP Debug] RecordsTable.render totalRecords: '.$this->totalRecords);
            Log::info('[MCP Debug] RecordsTable.render query SQL: '.$ledgerRecordsQuery->toSql());
            Log::info('[MCP Debug] RecordsTable.render query bindings: '.json_encode($ledgerRecordsQuery->getBindings()));

            // ページネーション実行
            $ledgerRecords = $ledgerRecordsQuery->simplePaginate($this->perPage);
            //            Log::info('RecordsTable render: ledgerRecords after simplePaginate', ['ledgerRecords' => $ledgerRecords->toArray()]);
        }

        // 表示される台帳レコードIDリストを取得
        $ledgerIds = $ledgerRecords->pluck('id');
        // 関連する添付ファイル情報を一括で取得
        $allAttachments = AttachedFile::whereIn('ledger_id', $ledgerIds)
            ->get()
            ->groupBy('ledger_id'); // ledger_id ごとにグループ化

        // 検索結果のフラグを設定
        $ledgerRecords->getCollection()->transform(function ($ledger) {
            // DBから取得したデータを正規化（二重エンコード等の破損データへの耐性を持たせる）
            // これにより、content_attached が文字列（破損データ）の場合でも配列に復元される。
            $ledger->content = $ledger->define->normalizeByColumnDefine($ledger->content ?? []);
            $ledger->content_attached = $ledger->define->normalizeByColumnDefine($ledger->content_attached ?? []);

            if (empty($ledger->content_attached) || empty($this->search)) {
                return $ledger;
            }
            $contentAttached = $ledger->content_attached;
            $hits = $this->searchContext->highlights;
            foreach ($contentAttached as $key => $attached) {
                if (empty($attached)) {
                    continue;
                }
                foreach ($attached as $hashedfilename => $metaData) {
                    if (\App\Helpers\SearchHelper::isFileDataHit($metaData, $hits)) {
                        if (is_array($metaData)) {
                            $contentAttached[$key][$hashedfilename]['hit'] = true;
                        } else {
                            $contentAttached[$key][$hashedfilename]->hit = true;
                        }
                    }
                }
            }
            $ledger->content_attached = $contentAttached;

            //            dd($ledger->content_attached,$hits);
            return $ledger;
        });

        $currentFolder = $this->currentFolder;
        $currentUserPermission = $currentFolder ? app(\App\Services\PermissionService::class)->getCurrentUserHighestPermission($currentFolder->id, 'Folder') : null;

        // Filter column_define for each ledgerDefine based on displayLevel
        $filteredColumnDefines = $ledgerDefineRecords->map(function ($ledgerDefine) {
            return collect($ledgerDefine->column_define)
                ->filter(function ($column) {
                    $columnDisplayLevel = $column->display_level ?? 3;

                    return $columnDisplayLevel <= $this->displayLevel;
                })
                ->sortBy('order');
        });

        // 台帳定義ごとのスコア統計を計算
        $scoreStatsByDefineId = $ledgerRecords->groupBy('ledger_define_id')->map(function ($records) {
            $scores = $records->pluck('composite_score')->filter(fn ($score) => $score > 0);

            return [
                'count' => $records->count(),
                'avg_score' => $scores->count() > 0 ? round($scores->avg(), 1) : 0,
                'max_score' => $scores->count() > 0 ? round($scores->max(), 1) : 0,
                'min_score' => $scores->count() > 0 ? round($scores->min(), 1) : 0,
                'has_scores' => $scores->count() > 0,
            ];
        });

        // 台帳定義をグループ化（順序を保持）
        // セマンティック検索時は既にソート済みなので、順序を維持したままグループ化
        $ledgerRecordsGroupByDefineIds = collect();
        foreach ($ledgerRecords as $ledger) {
            $defineId = $ledger->ledger_define_id;
            if (! $ledgerRecordsGroupByDefineIds->has($defineId)) {
                $ledgerRecordsGroupByDefineIds->put($defineId, collect());
            }
            $ledgerRecordsGroupByDefineIds->get($defineId)->push($ledger);
        }

        // 検索時は平均スコアの降順で台帳定義をソート
        if (! empty($this->search)) {
            $ledgerRecordsGroupByDefineIds = $ledgerRecordsGroupByDefineIds->sortByDesc(function ($records, $defineId) use ($scoreStatsByDefineId) {
                return $scoreStatsByDefineId[$defineId]['avg_score'] ?? 0;
            });
        } else {
            // 検索していない場合は、台帳定義のID順（または本来意図した順序）でソートを固定する
            // そうしないと、中のレコードの並び順（スコア順など）によって台帳カードの並びが変わってしまうため
            $ledgerRecordsGroupByDefineIds = $ledgerRecordsGroupByDefineIds->sortKeys();
        }

        Log::info('RecordsTable render end, returning view');

        return view('livewire.ledger.records-table', [
            'ledgerRecords' => $ledgerRecords,
            'ledgerRecordsGroupByDefineIds' => $ledgerRecordsGroupByDefineIds,
            'ledgerDefineRecordsKeyById' => $ledgerDefineRecords,
            'displayLevel' => $this->displayLevel,
            'currentFolder' => $currentFolder,
            'currentUserPermissionForFolder' => $currentUserPermission,
            'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
            'allAttachments' => $allAttachments,
            'filteredColumnDefines' => $filteredColumnDefines,
            'scoreStatsByDefineId' => $scoreStatsByDefineId,
            'keywords' => $this->keywords,
            'synonyms' => $this->synonyms,
            'highlights' => $this->highlights,
            'totalRecords' => $this->totalRecords,
        ]);
    }

    #[On('permissions-changed')]
    public function refreshDueToPermissionChange()
    {
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
            $attachedFile->update(['status' => \App\Enums\AttachedFileStatus::PENDING_INITIAL_PROCESSING->value]);
            \App\Jobs\Ledger\ProcessAttachedFile::dispatch($attachedFile);
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
