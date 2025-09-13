<?php

namespace App\Livewire\Folder;

use Illuminate\Support\Facades\Log;
use App\Http\Requests\Folder\StoreRequest;
use App\Models\Folder;
use Illuminate\Support\Collection;
use Livewire\Component;

class Create extends Component
{
    public $folderRecords;
    public $parentFolderId;
    public Collection $folderIdNameMap;

    public mixed $title;

    public function render()
    {
        return view('livewire.folder.create');
    }

    public function mount(StoreRequest $request)
    {
        // 認可チェックを追加
        $canCreate = auth()->user()->can('create', Folder::class);
        Log::info('Livewire/Folder/Create mount: User ID: ' . auth()->id() . ', Can create folder: ' . ($canCreate ? 'true' : 'false'));

        if (!$canCreate) {
            abort(403, __('auth.unauthorized')); // 権限がない場合は403を返す
        }

        $this->parentFolderId = $request->folderId();

        $this->folderRecords = [];
        $nodes = $this->folderRecords = Folder::get()->toTree();
        $traverse = function ($categories, $prefix = '-') use (&$traverse) {
            foreach ($categories as $category) {
                $category->title = $prefix . ' ' . $category->title;
                $this->folderRecords[] = $category;

                $traverse($category->children, $prefix . '-');
            }
        };

        $traverse($nodes);
        $this->folderRecords = collect($this->folderRecords);
        $this->folderIdNameMap = $this->folderRecords->mapWithKeys(function ($folderRecord) {
            $selected = $folderRecord->id == $this->parentFolderId ? true : false;

            return [
                $folderRecord->id => [
                    'id' => $folderRecord->id,
                    'name' => $folderRecord->title,
                    'selected' => $selected,
                ],
            ];
        });

    }

    public function store()
    {
        $parentFolderRecord = Folder::findOrFail($this->parentFolderId);


        $folderRecord = $parentFolderRecord->children()->create([
            'title' => $this->title,
            'creator_id' => auth()->id(),
            'modifier_id' => auth()->id(),
        ]);

        return redirect()->route('folder.edit', $folderRecord)
            ->with('status', __('ledger.folder.created'));

    }
}
