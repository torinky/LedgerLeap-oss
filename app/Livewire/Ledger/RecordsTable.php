<?php

namespace App\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest;
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
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RecordsTable extends Component
{
    use InitializesTenantContext, Toast,withPagination;

    public $perPage = 100;

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

    protected SynonymService $synonymService;

    protected SearchContext $searchContext;

    private $synonymServiceConfig;

    public bool $showPermissionModal = false;

    public bool $showActivityModal = false;

    public ?string $modalTitle = null;

    public ?int $modalResourceId = null;

    public ?string $modalResourceType = null;

    public ?string $currentTenantId = null;

    public bool $hasWorkflowEnabled = false;

    public string $orderByLabel = '';

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

        // composite_scoreカラムの存在確認
        if (! Schema::hasColumn('ledgers', 'composite_score')) {
            // マイグレーション未適用時のフォールバック
            $this->orderBy = 'id';
        }

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

        // ★ 追加: 初期orderByLabelの設定
        $this->orderByLabel = $this->getStandardSortLabel($this->orderBy);

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
        Log::info('sort method called', ['orderBy' => $this->orderBy, 'orderByLabel' => $this->orderByLabel]);
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
            Log::info('updatedOrderBy: custom sort option re-selected, reverting to default', ['orderBy' => $this->orderBy, 'orderByLabel' => $this->orderByLabel]);

            return; // これ以上処理しない
        }

        $this->orderByLabel = $this->getStandardSortLabel($value);
        Log::info('updatedOrderBy called', ['orderBy' => $this->orderBy, 'orderByLabel' => $this->orderByLabel]);
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
            default => '', // 標準ソート以外の場合は空文字列を返す
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
    public function render(SearchContext $searchContext)
    {
        // $this->authorize('viewAny', LedgerDefine::class);
        $this->initSearchContext();

        // Exportに検索条件を伝えるためにイベントをトリガ
        $this->dispatch('refreshChildren', data: [
            'keywords' => $this->searchContext->keywords,
            'filter' => $this->filter,
        ]);
        Log::info('render method called', ['orderBy' => $this->orderBy, 'orderByLabel' => $this->orderByLabel]);

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
        $ledgerRecords = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
//            ->search($this->searchContext)
            ->searchContext($this->searchContext)
            ->contentsFilter($this->filter)
            ->when(! empty($this->filterStatus), function ($query) {
                return $query->where('status', $this->filterStatus);
            })
//          重複データを持たないように
//          ->with('define.folder')
            ->orderBy('ledger_define_id', 'asc')
            ->when($this->orderBy === 'composite_score', function ($query) {
                // MySQLでは NULLS LAST が使えないため、スコア0を最後に
                return $query->orderByRaw('composite_score = 0, composite_score '.
                    ($this->orderAsc ? 'ASC' : 'DESC'));
            }, function ($query) {
                return $query->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
            });
        // dd($ledgerRecords);

        //      重複データを持たないように台帳定義とフォルダ情報は別に取得する
        $ledgerDefineRecords = LedgerDefine::whereIn('id', $ledgerRecords->get()->unique('ledger_define_id')->pluck('ledger_define_id')->toArray())
            ->with('folder')
            ->get()
            ->keyBy('id');

        // 台帳レコードの総数を取得
        $this->totalRecords = $ledgerRecords->count();

        // ページネーション実行
        $ledgerRecords = $ledgerRecords->simplePaginate($this->perPage);

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
                    foreach ($hits as $hit) {
                        // $metaDataが配列かオブジェクトかを判定
                        $content = null;
                        if (is_array($metaData) && isset($metaData['meta']['content'])) {
                            $content = $metaData['meta']['content'];
                        } elseif (is_object($metaData) && isset($metaData->meta->content)) {
                            $content = $metaData->meta->content;
                        }

                        if ($content !== null && stripos($content, $hit) !== false) {
                            // hitフラグも型で分岐
                            if (is_array($metaData)) {
                                $contentAttached[$key][$hashedfilename]['hit'] = true;
                            } else {
                                $contentAttached[$key][$hashedfilename]->hit = true;
                            }
                            break;
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

        // 台帳定義をグループ化し、検索時はスコア順にソート
        $ledgerRecordsGroupByDefineIds = $ledgerRecords->groupBy('ledger_define_id');

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
