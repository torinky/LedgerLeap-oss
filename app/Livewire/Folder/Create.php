<?php

namespace App\Livewire\Folder;

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

        return redirect()->route('folder.edit', ['folderId' => $folderRecord->id])
            ->with('status', __('ledger.folder.created'));

    }
}
