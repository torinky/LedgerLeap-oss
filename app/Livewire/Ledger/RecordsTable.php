<?php

namespace App\Livewire\Ledger;

use AllowDynamicProperties;
use App\Http\Requests\Ledger\SearchRequest;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Services\Config\SynonymServiceConfig;
use App\Services\Ledger\SearchContext;
use App\Services\SynonymService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

#[AllowDynamicProperties] class RecordsTable extends Component
{
    use withPagination;

    public $perPage = 100;

    #[Url(as: 'q')]
    public $search = '';

    public $orderBy = 'id';

    public $orderAsc = false;

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
        // 検索キーワードの初期化
        $search = $request->keyword();
        if (empty($this->search) && !empty($search)) {
            $this->search = $search ?? session()->get('search') ?? '';
        }
        $this->synonymServiceConfig = $synonymServiceConfig;
        $this->filter = $request->filter ?? [];
        $this->initSearchContext();

        // 現在のフォルダーIDを初期化
        $this->selectedFolderId = $request->folderId();
        $this->currentFolderId = $request->currentFolderId();

        // もし台帳IDが指定されていれば、選択済みリストに追加
        if ($request->ledgerDefineId()) {
            $this->selectedLedgerDefineIds = [$request->ledgerDefineId()];
        }

        // フォルダーアセットを準備
        $this->prepareFolderAsset();
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
        if (!$this->synonymServiceConfig) {
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
    }

    /**
     * 列のソートを行う
     *
     * @param string $columnName
     * @return void
     */
    public function sort($columnName)
    {
        $this->orderBy = $columnName;

        // 現在のソート順をトグル
        $this->orderAsc = !$this->orderAsc;

        $this->initSearchContext();
        $this->render($this->searchContext);
    }

    /**
     * コンポーネントの表示を更新する
     *
     * @return Application|Factory|View
     */
    #[On('ledgerStored')]
    public function render(SearchContext $searchContext)
    {
        $this->authorize('view_ledger_defines', LedgerDefine::class);
        $this->initSearchContext();

        // Exportに検索条件を伝えるためにイベントをトリガ
        $this->dispatch('refreshChildren', data: [
            'keywords' => $this->searchContext->keywords,
            'filter' => $this->filter,
        ]);

        // 表示対象の台帳を取得
        $displayLedgerDefines = LedgerDefine::WhereIn('id', $this->selectedLedgerDefineIds)
            ->searchTags($this->searchContext->tags)
            ->with('folder')
            ->get();

        $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];

        $breadcrumbsPerLedgerDefine = [];
        foreach ($displayLedgerDefines as $displayLedgerDefine) {
            // 台帳ごとのパンくずリストを準備
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id] = $displayLedgerDefine->folder->parent()->get();
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id][] = $displayLedgerDefine->folder;
        }

        // 表示対象の台帳に紐づく仕訳データを取得
        $ledgerRecords = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
//            ->search($this->searchContext)
            ->searchContext($this->searchContext)
            ->contentsFilter($this->filter)
//          重複データを持たないように
//          ->with('define.folder')
            ->orderBy('ledger_define_id', 'asc')
            ->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc');
        //dd($ledgerRecords);

        //      重複データを持たないように台帳定義とフォルダ情報は別に取得する
        $ledgerDefineRecords = LedgerDefine::whereIn('id', $ledgerRecords->get()->unique('ledger_define_id')->pluck('ledger_define_id')->toArray())
            ->with('folder')
            ->get()
            ->keyBy('id');

        $canCreate = [];
        $canUpdate = [];
        $canView = [];
        foreach ($ledgerDefineRecords as $ledgerDefine) {
            $canCreate[$ledgerDefine->id] = Gate::allows('create', [Ledger::class, $ledgerDefine]);
            $canUpdate[$ledgerDefine->id] = Gate::allows('update', [Ledger::class, $ledgerDefine]);
            $canView[$ledgerDefine->id] = Gate::allows('view', [Ledger::class, $ledgerDefine]);
        }

        // 台帳レコードの総数を取得
        $this->totalRecords = $ledgerRecords->count();

        //ページネーション実行
        $ledgerRecords = $ledgerRecords->simplePaginate($this->perPage);

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
                        if (stripos($metaData->meta->content, $hit) !== false) {
                            $contentAttached[$key][$hashedfilename]->hit = true;
                            break;
                        }
                    }
                }
            }
            $ledger->content_attached = $contentAttached;

            //            dd($ledger->content_attached,$hits);
            return $ledger;
        });

        return view('livewire.ledger.records-table', [
            'ledgerRecords' => $ledgerRecords,
            //          表示用のledgerRecords（View側で変則的な表示をしないように台帳ごとにレコードをまとめておく）
            'ledgerRecordsGroupByDefineIds' => $ledgerRecords->groupBy('ledger_define_id'),
            'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
            'totalRecords' => $this->totalRecords,
            'ledgerDefineRecordsKeyById' => $ledgerDefineRecords,
            'canCreate' => $canCreate,
            'canUpdate' => $canUpdate,
            'canView' => $canView,
        ]);
    }

    /**
     * 選択する台帳を1つにする
     *
     * @param int $defineId
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
     * @param int $newFolderId
     * @return void
     */
    public function changeCurrentFolder($newFolderId)
    {
        if ($newFolderId == 1) {
            $this->selectedFolderIds = [];
            $this->selectedLedgerDefineIds = [];
        } else {
            if ($newFolderId == $this->currentFolderId && !empty($this->selectedFolderIds)) {
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
     * @param int $targetLedgerDefineId
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
        $currentFolder = Folder::findOrFail($this->currentFolderId);

        if (!empty($currentFolder)) {
            $this->breadcrumbs = $currentFolder->ancestors()->get();
        }
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)->get();
    }

    /**
     * フォルダの選択状態をトグルする
     *
     * @param int $folderId
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
     * @param int $ledgerDefineId
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
}
