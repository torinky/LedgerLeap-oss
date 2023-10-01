<?php

namespace App\Http\Livewire\Ledger;

use App\Http\Requests\Ledger\SearchRequest;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class RecordsTable extends Component
{
    use withPagination;

    public $perPage = 100;
    public $search = '';
    public $orderBy = 'id';
    public $orderAsc = false;
    public $filter = [];
    public $defineId = null;
    public $ledgerDefineRecords;
    public $folderRecords;
    public $breadcrumbs = [];
    public $selectedLedgerDefineIds = [];
    public $selectedFolderIds = [];
    public $currentFolderId;
    protected $listeners = ['contentsFilter', 'currentFolderChangedByTree'];
    private $tags = [];
    public $keywords = [];
    public $totalRecords;

    /**
     * コンポーネントが初めてリクエストされた時に実行される初期化処理
     *
     * @param SearchRequest $request
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function mount(SearchRequest $request)
    {
        // 検索キーワードの初期化
        $search = $request->keyword();
        if (empty($this->search) && !empty($search)) {
            $this->search = $search ?? session()->get('search') ?? '';
        }
        $this->updateKeywordsAndTags($this->search);

        // 現在のフォルダーIDを初期化
        $this->currentFolderId = $request->folderId();

        // もし台帳IDが指定されていれば、選択済みリストに追加
        if ($request->ledgerDefineId()) {
            $this->selectedLedgerDefineIds = [$request->ledgerDefineId()];
        }

        // フォルダーアセットを準備
        $this->prepareFolderAsset();
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

        $this->render();
    }

    /**
     * コンポーネントの表示を更新する
     *
     * @return Application|Factory|View
     */
    public function render()
    {
        // checkboxのキーはサーバー側で変えるとブラウザに正しく反映されなくなる
        $this->selectedFolderIds = array_filter($this->selectedFolderIds, 'strlen');

        $this->updateKeywordsAndTags($this->search);
        // Exportに検索条件を伝えるためにイベントをトリガ
        $this->emit('refreshChildren', ['keywords' => $this->keywords, 'filter' => $this->filter]);

        $descendantFolderIds = [];
        foreach ($this->selectedFolderIds as $selectedFolderId) {
            $descendantFolderIds = array_merge(
                Folder::whereDescendantOf($selectedFolderId)
                    ->pluck('id')->toArray(),
                $descendantFolderIds
            );
        }

        // 表示対象の台帳を取得
        $displayLedgerDefines = LedgerDefine::whereIn('folder_id', array_merge($this->selectedFolderIds, $descendantFolderIds))
            ->orWhereIn('id', $this->selectedLedgerDefineIds)
            ->searchTags($this->tags)
            ->with('folder')
            ->get();

        $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];

        $breadcrumbsPerLedgerDefine = [];
        foreach ($displayLedgerDefines as $displayLedgerDefine) {
            // 台帳ごとのパンくずリストを準備
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id] = $displayLedgerDefine->folder->parents();
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id][] = $displayLedgerDefine->folder;
        }

        // 表示対象の台帳に紐づく仕訳データを取得
        $ledgerRecords = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
            ->search(implode(' ', $this->keywords))
            ->contentsFilter($this->filter)
            ->with('define.folder');

        // 仕訳データの総数を取得
        $this->totalRecords = $ledgerRecords->count();

        return view('livewire.ledger.records-table', [
            'ledgerRecords' =>
            /*                    Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
                                    ->search(implode(' ', $this->keywords))->contentsFilter($this->filter)
                                    ->with('define.folder')*/
                $ledgerRecords
                    ->orderBy('ledger_define_id', 'asc')
                    ->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc')
                    ->simplePaginate($this->perPage),
            'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
        ]);
    }

    /**
     * コンテンツのフィルタリングを行う
     *
     * @param int $defineId
     * @param int $columnNo
     * @param string $word
     * @return void
     */
    public function contentsFilter($defineId, $columnNo, $word)
    {
        $this->defineId = $defineId;
        $this->filter[$columnNo] = $word;
        $this->render();
    }

    /**
     * 入力されたテキストからキーワードとタグを更新する
     *
     * @param string $rawInputText
     * @return void
     */
    private function updateKeywordsAndTags($rawInputText)
    {
        $text = mb_convert_kana($rawInputText, 'askV', 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        $words = explode(' ', $text);
        $words = array_filter($words, 'strlen');

        if (empty($words)) {
            return;
        }

        $this->keywords = [];
        $this->tags = [];

        foreach ($words as $word) {
            if (Str::startsWith($word, '#')) {
                $this->tags[] = substr($word, 1);
            } else {
                $this->keywords[] = $word;
            }
        }
    }

    /**
     * 現在のフォルダーを変更する
     *
     * @param int $newFolderId
     * @return void
     */
    public function changeCurrentFolder($newFolderId)
    {
        // フォルダーIDを更新
        $this->currentFolderId = $newFolderId;

        $this->selectedLedgerDefineIds = [];

        // フォルダーアセットを再準備
        $this->prepareFolderAsset();

        // ツリーコンポーネントに現在のフォルダーIDの変更を通知
        $this->emit('currentFolderChangedByMain', $this->currentFolderId);
    }

    /**
     * ツリーコンポーネントから通知を受け取り、現在のフォルダーを変更する
     *
     * @param int $newFolderId
     * @return void
     */
    public function currentFolderChangedByTree($newFolderId)
    {
        // フォルダーIDを更新
        $this->currentFolderId = $newFolderId;

        $this->selectedLedgerDefineIds = [];

        // フォルダーアセットを再準備
        $this->prepareFolderAsset();
    }

    /**
     * 台帳を開閉する（コメントアウト済みのコード）
     *
     * @param int $targetLedgerDefineId
     * @return void
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
     *
     * @return void
     */
    public function prepareFolderAsset(): void
    {
        $currentFolder = Folder::where('id', '=', $this->currentFolderId)->first();

        if (!empty($currentFolder)) {
            $this->breadcrumbs = $currentFolder->parents();
        }
        $this->breadcrumbs[] = $currentFolder;

        $this->folderRecords = $currentFolder->children()->get();
        $this->ledgerDefineRecords = LedgerDefine::where('folder_id', '=', $this->currentFolderId)->get();

        if (!$currentFolder->isRoot()) {
            $this->selectedFolderIds = $this->folderRecords->pluck('id')->toArray();
            $this->selectedLedgerDefineIds = $this->ledgerDefineRecords->pluck('id')->toArray();
        }
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
