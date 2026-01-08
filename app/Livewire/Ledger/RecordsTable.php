<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest;
use App\Livewire\BaseLivewireComponent;
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
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RecordsTable extends BaseLivewireComponent
{
    use InitializesTenantContext, Toast,withPagination;

    public $perPage = 100;

    public string|int|null $currentTenantId = null;

    #[Url(as: 'q')]
    public $search = '';

    public $orderBy = 'composite_score';

    public $orderAsc = false;

    public $filterStatus = '';

    #[Url(as: 'fi')]
    public $filter = [];

    public $defineId = null;

    public $ledgerDefineRecords;

    public $folderRecords;

    public $breadcrumbs = [];

    #[Url(as: 'l')]
    public $selectedLedgerDefineIds = [];

    #[Url(as: 'f')]
    public $selectedFolderIds = [];

    #[Url(as: 'cf')]
    public $currentFolderId;

    #[Url(as: 'dl')]
    public int $displayLevel = 1;

    private $tags = [];

    public $keywords = [];

    public $totalRecords;

    public $highlights = [];

    public array $synonyms;

    public $useSynonym = true;

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

    public array $defaultSortColumns = []; // 追加: デフォルトソートカラムを保持

    #[Url(as: 'sem', history: true)]
    public bool $useSemanticSearch = false;

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

        // 検索キーワードの初期化
        $search = $request->keyword();
        if (empty($this->search) && ! empty($search)) {
            $this->search = $search;
        } elseif (empty($this->search)) {
            $this->search = session()->get('search', '');
        }
        $this->synonymServiceConfig = $synonymServiceConfig;
        $this->filter = $request->filter ?? [];
        $this->initSearchContext();

        // 現在のフォルダーIDを初期化
        // URLパラメータ 'f' (selectedFolderIds) が存在する場合はそれを優先
        if (empty($this->selectedFolderIds) && $request->folderId()) {
            $this->selectedFolderIds = [$request->folderId()];
        } elseif (empty($this->selectedFolderIds)) {
            $this->selectedFolderIds = []; // デフォルトは空
        }

        $this->currentFolderId = $request->currentFolderId();

        // もし台帳IDが指定されていれば、選択済みリストに追加
        // URLパラメータ 'l' (selectedLedgerDefineIds) が存在する場合はそれを優先
        if (empty($this->selectedLedgerDefineIds) && $request->ledgerDefineId()) {
            $this->selectedLedgerDefineIds = [$request->ledgerDefineId()];
            $this->defineId = $request->ledgerDefineId();
        } elseif (empty($this->selectedLedgerDefineIds)) {
            $this->selectedLedgerDefineIds = []; // デフォルトは空
        }

        // displayLevelがURLクエリ文字列から設定されている場合、その値を使用
        // そうでない場合、または不正な値の場合はデフォルトの1を使用
        if (! in_array($this->displayLevel, [1, 2, 3])) {
            $this->displayLevel = 1;
        }

        // フォルダーアセットを準備
        $this->prepareFolderAsset();

        $this->hasWorkflowEnabled = $this->ledgerDefineRecords->contains('workflow_enabled', true);

        // ★★★ デフォルトソートカラムのロードとorderByの初期化 ★★★
        if (count($this->selectedLedgerDefineIds) === 1) {
            $singleLedgerDefineId = head($this->selectedLedgerDefineIds);
            $singleLedgerDefine = LedgerDefine::find($singleLedgerDefineId);

            if ($singleLedgerDefine) {
                $this->defaultSortColumns = collect($singleLedgerDefine->column_define)
                    ->filter(fn ($column) => $column->sort_index !== null)
                    ->sortBy('sort_index')
                    ->map(fn ($column) => $column->toArray()) // ColumnDefineオブジェクトを配列に変換
                    ->values() // キーをリセット
                    ->toArray();

                if (! empty($this->defaultSortColumns)) {
                    // デフォルトソートカラムが設定されている場合、orderByを'default'に設定
                    $this->orderBy = 'default';
                    $this->orderAsc = true; // デフォルトソートは常に昇順
                }
            }
        }

        // composite_scoreカラムの存在確認 (デフォルトソート設定がない場合にのみ適用)
        if ($this->orderBy !== 'default' && ! Schema::hasColumn('ledgers', 'composite_score')) {
            // マイグレーション未適用時のフォールバック
            $this->orderBy = 'id';
        }

        // 初期orderByLabelの設定
        $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);
    }

    /**
     * 検索コンテキストを初期化
     *
     * 検索コンテキストを作成し、検索キーワード、フィルター、タグ、キーワード、ハイライト、およびシノニムを設定します。
     * また、検索コンテキストの構成に使用するシノニムサービスの設定も行います。
     *
     * @return void
     */
    protected function initSearchContext()
    {
        if (! $this->synonymServiceConfig) {
            $this->synonymServiceConfig = new SynonymServiceConfig([
                'useSynonym' => $this->useSynonym,
                'useTechnicalTerm' => $this->useTechnicalTerm,
            ]);
        }

        $synonymService = new SynonymService($this->synonymServiceConfig);
        $this->searchContext = new SearchContext($synonymService);

        $this->searchContext->setSearch($this->search);
        $this->searchContext->setFilter($this->filter);
        $this->tags = $this->searchContext->tags;
        $this->keywords = $this->searchContext->keywords;
        $this->highlights = $this->searchContext->highlights;
        $this->synonyms = $this->searchContext->synonyms;
        //        dd($this->searchContext,$this->keywords);
    }

    /**
     * 列のソートを行う
     *
     * @param  string  $columnName
     * @param  string|null  $columnLabel
     * @return void
     */
    public function sort($columnName, $columnLabel = null)
    {
        $this->orderBy = $columnName;

        // 現在のソート順をトグル
        $this->orderAsc = ! $this->orderAsc;

        // ★ 追加: orderByLabelの設定
        $this->orderByLabel = $columnLabel ?? $this->getStandardSortLabel($columnName);

        $this->initSearchContext();
    }

    /**
     * orderByが変更されたときにorderByLabelを更新するライフサイクルフック
     */
    public function updatedOrderBy($value)
    {
        // ユーザーがカスタムソートのオプションを再度選択した場合、デフォルトのソートに戻す
        if ($this->getStandardSortLabel($value) === '' && $value === $this->orderBy) {
            $this->orderBy = 'composite_score'; // デフォルトのソートに戻す
            $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);

            return; // これ以上処理しない
        }

        $this->orderByLabel = $this->getStandardSortLabel($value);
    }

    /**
     * 検索語が変更されたときに実行されるライフサイクルフック
     */
    public function updatedSearch($value)
    {
        $this->initSearchContext();
    }

    /**
     * セマンティック検索トグルが変更されたときに実行されるライフサイクルフック
     */
    public function updatedUseSemanticSearch($value)
    {
        if ($value) {
            // セマンティック検索ON: 同義語の状態を保存してOFFにする
            $this->savedUseSynonymState = $this->useSynonym;
            $this->useSynonym = false;
        } else {
            // セマンティック検索OFF: 同義語の状態を復元
            if ($this->savedUseSynonymState !== null) {
                $this->useSynonym = $this->savedUseSynonymState;
                $this->savedUseSynonymState = null;
            }

            // semantic_scoreが選択されていたらcomposite_scoreに戻す
            if ($this->orderBy === 'semantic_score') {
                $this->orderBy = 'composite_score';
                $this->orderByLabel = $this->getStandardSortLabel('composite_score');
            }
        }

        // SearchContextを再初期化
        $this->initSearchContext();
    }

    /**
     * 標準ソートのラベルを取得するヘルパーメソッド
     */
    private function getStandardSortLabel(string $columnName): string
    {
        return match ($columnName) {
            'composite_score' => __('ledger.scoring.score'),
            'created_at' => __('ledger.created_at'),
            'updated_at' => __('ledger.updated_at'),
            'semantic_score' => __('ledger.semantic_score_sort'),
            default => '', // 標準ソート以外の場合は空文字列を返す
        };
    }

    /**
     * コレクションに対してソートを適用する
     * セマンティック検索時に使用
     *
     * @param  \Illuminate\Support\Collection  $collection
     * @return \Illuminate\Support\Collection
     */
    private function applySorting($collection, string $orderBy, bool $orderAsc)
    {
        return match ($orderBy) {
            'semantic_score' => $orderAsc
                ? $collection->sortBy('semantic_score')
                : $collection->sortByDesc('semantic_score'),
            'composite_score' => $orderAsc
                ? $collection->sortBy(fn ($ledger) => $ledger->composite_score ?: 0)
                : $collection->sortByDesc(fn ($ledger) => $ledger->composite_score ?: 0),
            'created_at' => $orderAsc
                ? $collection->sortBy('created_at')
                : $collection->sortByDesc('created_at'),
            'updated_at' => $orderAsc
                ? $collection->sortBy('updated_at')
                : $collection->sortByDesc('updated_at'),
            default => $collection // フォールバック: ソートしない
        };
    }

    /**
     * 表示レベルを設定する
     */
    public function setDisplayLevel(int $level): void
    {
        if (! in_array($level, [1, 2, 3])) {
            // 不正なレベルが指定された場合は何もしないか、エラーをログに記録
            return;
        }
        $this->displayLevel = $level;
    }

    /**
     * コンポーネントの表示を更新する
     *
     * @return Application|Factory|View
     */
    #[On('ledgerStored')]
    #[On('permissions-changed')]
    public function refresh()
    {
        $this->prepareFolderAsset();
    }

    #[On('ledgerStored')]
    public function render()
    {
        // $this->authorize('viewAny', LedgerDefine::class);
        $this->initSearchContext();

        // Exportに検索条件を伝えるためにイベントをトリガ
        $this->dispatch('refreshChildren', data: [
            'keywords' => $this->searchContext->keywords,
            'filter' => $this->filter,
        ]);

        // グローバル検索かどうかの判定
        $isGlobalSearch = ! empty($this->search) && empty($this->selectedLedgerDefineIds) && empty($this->selectedFolderIds);

        if ($isGlobalSearch) {
            // グローバル検索の場合、すべての台帳定義を対象にする
            $displayLedgerDefines = LedgerDefine::query()
                ->searchTags($this->searchContext->tags)
                ->with('folder')
                ->get();
            $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];
        } else {
            // 通常の場合、選択された台帳定義のみを対象にする
            $displayLedgerDefines = LedgerDefine::WhereIn('id', $this->selectedLedgerDefineIds)
                ->searchTags($this->searchContext->tags)->with('folder')
//            $displayLedgerDefines = $displayLedgerDefines->with('folder')
                ->get();
            $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];
        }

        $breadcrumbsPerLedgerDefine = [];
        foreach ($displayLedgerDefines as $displayLedgerDefine) {
            // 台帳ごとのパンくずリストを準備
            if ($displayLedgerDefine->folder) {
                $ancestors = $displayLedgerDefine->folder->ancestors()->get();
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

            Log::info('Semantic Search Query', [
                'original' => $this->search,
                'query_for_embedding' => $searchQuery,
                'keywords' => $this->searchContext->keywords,
                'useSynonym' => $this->useSynonym,
                'useTechnicalTerm' => $this->useTechnicalTerm,
                'note' => 'Semantic search uses keywords only, not synonyms',
            ]);

            // Step 2: RAGで検索（スコア情報付きで全件取得）
            $ragResults = app(\App\Services\RagSearchService::class)->searchLedgers(
                query: $searchQuery,
                limit: 1000, // 十分な件数を取得（後でソート・ページネーション）
                filters: array_merge($this->filter, [
                    'user' => auth()->user(),
                    'ledger_define_ids' => $searchTargetLedgerDefineIds,
                ])
            );

            Log::info('Semantic Search Results', [
                'query' => $searchQuery,
                'results_count' => count($ragResults),
                'first_result' => ! empty($ragResults) ? $ragResults[0] : null,
            ]);

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
                    ->with(['define', 'creator', 'modifier'])
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
                    ->with('folder')
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
                ->orderBy('ledger_define_id', 'asc')
                ->when($this->orderBy === 'default', function ($query) {
                    // defaultSortColumns を使用してソートを適用
                    foreach ($this->defaultSortColumns as $column) {
                        $columnId = $column['id'];
                        $columnType = $column['type'];

                        // JSON_EXTRACT を使ってパスを構築（$[0] 形式で配列インデックスアクセス）
                        $jsonPath = "JSON_EXTRACT(`content`, '$[{$columnId}]')";

                        // 型に応じたキャスト
                        $expression = match ($columnType) {
                            'number', 'auto_number' => "CAST({$jsonPath} AS DECIMAL(20, 6))",
                            'date', 'YMD' => "CAST({$jsonPath} AS DATE)",
                            default => $jsonPath,
                        };

                        $query->orderByRaw("{$expression} ".($this->orderAsc ? 'ASC' : 'DESC'));
                    }
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
            $ledgerDefineRecords = LedgerDefine::whereIn('id', (clone $ledgerRecordsQuery)->get()->unique('ledger_define_id')->pluck('ledger_define_id')->toArray())
                ->with('folder')
                ->get()
                ->keyBy('id');

            // 総数を取得
            $this->totalRecords = $ledgerRecordsQuery->count();

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

        $currentFolder = Folder::find($this->currentFolderId);
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
        }

        return view('livewire.ledger.records-table', [
            'ledgerRecords' => $ledgerRecords,
            //          表示用のledgerRecords（View側で変則的な表示をしないように台帳ごとにレコードをまとめておく）
            'ledgerRecordsGroupByDefineIds' => $ledgerRecordsGroupByDefineIds,
            'allAttachments' => $allAttachments, // ★ ビューに渡す
            'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
            'totalRecords' => $this->totalRecords,
            'ledgerDefineRecordsKeyById' => $ledgerDefineRecords,
            'currentFolder' => $currentFolder,
            'currentUserPermissionForFolder' => $currentUserPermission,
            'filteredColumnDefines' => $filteredColumnDefines, // Pass filtered columns to the view
            'scoreStatsByDefineId' => $scoreStatsByDefineId, // スコア統計
            'currentTenantId' => $this->currentTenantId,
        ]);
    }

    #[On('permissions-changed')]
    public function refreshDueToPermissionChange()
    {
        // このメソッドが存在し、イベントをリッスンするだけで、
        // Livewireがコンポーネントを再レンダリングし、render()が自動的に呼び出される
    }

    /**
     * 選択する台帳を1つにする
     *
     * @param  int  $defineId
     * @return void
     */
    #[On('focusLedgerDefine')]
    public function focusLedgerDefine($defineId)
    {
        $this->defineId = $defineId;
        $this->selectedLedgerDefineIds = [$defineId];
    }

    /**
     * 現在のフォルダーを変更する
     *
     * @param  int  $newFolderId
     * @return void
     */
    public function changeCurrentFolder($newFolderId)
    {
        if ($newFolderId == 1) {
            $this->selectedFolderIds = [];
            $this->selectedLedgerDefineIds = [];
        } else {
            if ($newFolderId == $this->currentFolderId && ! empty($this->selectedFolderIds)) {
                $this->selectedFolderIds = [];
            } else {
                $this->selectedFolderIds = Folder::descendantsAndSelf($newFolderId)->pluck('id')->toArray();
                $this->selectedLedgerDefineIds = LedgerDefine::whereIn('folder_id', $this->selectedFolderIds)->pluck('id')->toArray();
            }
        }
        $this->currentFolderId = $newFolderId;

        $this->dispatch('currentFolderChangedByMain', newFolderId: $this->currentFolderId, newSelectedFolderIds: $this->selectedFolderIds);

        $this->prepareFolderAsset();
    }

    #[On('currentFolderChangedByTree')]
    public function changeCurrentFolderByTree($newFolderId, $newSelectedFolderIds)
    {
        if ($newFolderId == 1) {
            $this->selectedLedgerDefineIds = [];
        }
        // フォルダーIDを更新
        $this->currentFolderId = $newFolderId;

        $this->selectedFolderIds = $newSelectedFolderIds;
        $this->selectedLedgerDefineIds = LedgerDefine::whereIn('folder_id', $this->selectedFolderIds)->pluck('id')->toArray();

        // フォルダーアセットを再準備
        $this->prepareFolderAsset();

    }

    /**
     * 台帳を開閉する（コメントアウト済みのコード）
     *
     * @param  int  $targetLedgerDefineId
     */
    /*
    public function toggleLedgerDefineOpen($targetLedgerDefineId)
    {
        if (in_array($targetLedgerDefineId, $this->selectedLedgerDefineIds)) {
            $this->selectedLedgerDefineIds = collect($this->selectedLedgerDefineIds)->reject(function ($item) use ($targetLedgerDefineId) {
                return ($item === $targetLedgerDefineId) || ($item === false);
            })->toArray();
        } else {
            $this->selectedLedgerDefineIds[] = $targetLedgerDefineId;
        }
    }
    */

    /**
     * フォルダーアセットを準備する
     */
    public function prepareFolderAsset(): void
    {
        // currentFolderId が未設定、または別テナントのID/存在しないIDの可能性があるためガードする
        $currentFolder = null;

        if (! empty($this->currentFolderId)) {
            $currentFolder = Folder::find($this->currentFolderId);
        }

        // 指定IDで見つからない場合はテナントのルートフォルダを試す
        if (! $currentFolder) {
            $currentFolder = Folder::root()->first();

            if ($currentFolder) {
                $this->currentFolderId = $currentFolder->id;
            }
        }

        // それでも見つからなければ、例外にせず空データで返す（UI崩壊防止）
        if (! $currentFolder) {
            $this->breadcrumbs = [];
            $this->folderRecords = collect();
            $this->ledgerDefineRecords = collect();

            return;
        }

        $this->breadcrumbs = $currentFolder->ancestors()->get()->all();
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)->get();
    }

    /**
     * フォルダの選択状態をトグルする
     *
     * @param  int  $folderId
     * @return void
     */
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

        $this->dispatch('selectedFolderChangedByMain', newSelectedFolderIds: $this->selectedFolderIds);

        $this->resetPage();
    }

    /**
     * 台帳の選択状態をトグルする
     *
     * @param  int  $ledgerDefineId
     * @return void
     */
    public function toggleLedgerDefineId($ledgerDefineId)
    {
        if (in_array($ledgerDefineId, $this->selectedLedgerDefineIds)) {
            // 選択済みの場合、リストから削除
            $this->selectedLedgerDefineIds = array_values(array_diff($this->selectedLedgerDefineIds, [$ledgerDefineId]));
        } else {
            // 選択されていない場合、リストに追加
            $this->selectedLedgerDefineIds[] = $ledgerDefineId;
        }
        $this->resetPage();
    }

    /**
     * ページネーションの総ページ数を計算する
     *
     * @return int
     */
    public function lastPage()
    {
        return ceil($this->totalRecords / $this->perPage);
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
}
