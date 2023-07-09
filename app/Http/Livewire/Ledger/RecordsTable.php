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
    private array $tags = [];
    private array $keywords = [];
    public $totalRecords;

    /**
     * 初回リクエストの時はこちらで初期化される
     * @param SearchRequest $request
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function mount(searchRequest $request)
    {
        $search = $request->keyword();
        if (empty($this->search) && !empty($search)) {
            $this->search = $search ?? session()->get('search') ?? '';
        }
        $this->updateKeyworrdsAndTags($this->search);

        $this->currentFolderId = $request->folderId();
        if ($request->ledgerDefineId()) {
            $this->selectedLedgerDefineIds = [$request->ledgerDefineId()];
        }
        $this->prepareFolderAsset();
    }

    public function sort($columnName)
    {
        $this->orderBy = $columnName;

        if ($this->orderAsc) {
            $this->orderAsc = false;
        } else {
            $this->orderAsc = true;
        }

        $this->render();
    }

    /**
     * ajaxリクエストの処理はこちら
     * @return Application|Factory|View
     */
    public function render()
    {
        // checkboxのキーはサーバー側で変えるとブラウザに正しく反映されなくなる
        $this->selectedFolderIds = array_filter($this->selectedFolderIds, 'strlen');

        $this->updateKeyworrdsAndTags($this->search);

        $descendantFolderIds = [];
        foreach ($this->selectedFolderIds as $selectedFolderId) {
            $descendantFolderIds = array_merge(
                Folder::whereDescendantOf($selectedFolderId)
                    ->pluck('id')->toArray()
                , $descendantFolderIds
            );
        }

        $displayLedgerDefines = LedgerDefine::whereIn('folder_id',
            array_merge($this->selectedFolderIds, $descendantFolderIds)
        )
            ->orWhereIn('id', $this->selectedLedgerDefineIds)
            ->searchTags($this->tags)
            ->with('folder')
            ->get();

        $searchTargetLedgerDefineIds = $displayLedgerDefines->pluck('id')->toArray() ?? [];

        $breadcrumbsPerLedgerDefine = [];
        foreach ($displayLedgerDefines as $displayLedgerDefine) {
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id] = $displayLedgerDefine->folder->parents();
            $breadcrumbsPerLedgerDefine[$displayLedgerDefine->id][] = $displayLedgerDefine->folder;
        }


        $ledgerRecords = Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
            ->search(implode(' ', $this->keywords))->contentsFilter($this->filter)
            ->with('define.folder');
        $this->totalRecords = $ledgerRecords->count();

        return view('livewire.ledger.records-table'
            , [
                'ledgerRecords' =>
                /*                    Ledger::whereIn('ledger_define_id', $searchTargetLedgerDefineIds)
                                        ->search(implode(' ', $this->keywords))->contentsFilter($this->filter)
                                        ->with('define.folder')*/
                    $ledgerRecords
                        ->orderBy('ledger_define_id', 'asc')
                        ->orderBy($this->orderBy, $this->orderAsc ? 'asc' : 'desc')
                        ->simplePaginate($this->perPage),
                'breadcrumbsPerLedgerDefine' => $breadcrumbsPerLedgerDefine,
            ]
        );
    }

    public function contentsFilter($defineId, $columnNo, $word)
    {
//        dd($defineId,$columnNo,$word);
        $this->defineId = $defineId;
        $this->filter[$columnNo] = $word;
        $this->render();

    }

    /**
     * @param string $rawInputText
     * @return void
     */
    private function updateKeyworrdsAndTags($rawInputText)
    {
        $text = mb_convert_kana($rawInputText, 'askV', 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        $words = explode(' ', $text);
        foreach ($words as $word) {
            if (Str::startsWith($word, '#')) {
                $this->tags[] = substr($word, 1);
            } else {
                $this->keywords[] = $word;
            }
        }
    }


    public function changeCurrentFolder($newFolderId)
    {
        $this->currentFolderId = $newFolderId;
        $this->prepareFolderAsset();

        $this->emit('currentFolderChangedByMain', $this->currentFolderId);

    }

    public function currentFolderChangedByTree($newFolderId)
    {
        $this->currentFolderId = $newFolderId;

        $this->prepareFolderAsset();
    }

    /*
       public function toggleLedgerDefineOpen($targetLedgerDefineId)
        {
            if (in_array($targetLedgerDefineId,$this->selectedLedgerDefineIds)) {
                $this->selectedLedgerDefineIds = collect($this->selectedLedgerDefineIds)->reject(function ($item) use ($targetLedgerDefineId) {
                    return ($item === $targetLedgerDefineId)||($item===false);
                })->toArray();
            }else{
                $this->selectedLedgerDefineIds[]=$targetLedgerDefineId;
            }
        }
    */
    /**
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

    public function toggleLedgerDefineId($ledgerDefineId)
    {
        if (in_array($ledgerDefineId, $this->selectedLedgerDefineIds)) {
            $this->selectedLedgerDefineIds = array_values(array_diff($this->selectedLedgerDefineIds, [$ledgerDefineId]));
        } else {
            $this->selectedLedgerDefineIds[] = $ledgerDefineId;
        }
    }

    public function lastPage()
    {
        return ceil($this->totalRecords / $this->perPage);
    }

}
