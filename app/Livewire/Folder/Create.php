<?php

namespace App\Livewire\Folder;

use App\Http\Requests\Folder\StoreRequest;
use App\Livewire\Traits\InitializesTenantContext;
use App\Livewire\Traits\HasFolderTree;
use App\Models\Folder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Create extends BaseLivewireComponent
{
    use InitializesTenantContext, HasFolderTree;

    public $parentFolderId;

    public mixed $title;

    public function render()
    {
        return view('livewire.folder.create');
    }

    public function mount(StoreRequest $request)
    {
        // 認可チェックを追加
        $canCreate = auth()->user()->can('create', Folder::class);
        Log::info('Livewire/Folder/Create mount: User ID: '.auth()->id().', Can create folder: '.($canCreate ? 'true' : 'false'));

        if (! $canCreate) {
            abort(403, __('auth.unauthorized')); // 権限がない場合は403を返す
        }

        $this->parentFolderId = $request->folderId();

        $this->initializeFolderTree($this->parentFolderId);
    }

    public function store()
    {
        $parentFolderRecord = Folder::findOrFail($this->parentFolderId);

        $folderRecord = $parentFolderRecord->children()->create([
            'title' => $this->title,
            'creator_id' => auth()->id(),
            'modifier_id' => auth()->id(),
        ]);

        return redirect()->route('folder.edit', ['tenant' => tenant()->id, 'folder' => $folderRecord])
            ->with('status', __('ledger.folder.created'));

    }
}
