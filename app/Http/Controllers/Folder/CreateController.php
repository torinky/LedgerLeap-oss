<?php

namespace App\Http\Controllers\Folder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Folder\StoreRequest;
use App\Models\Folder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    public function create(StoreRequest $request)
    {
        $rootFolder = Folder::root()->get();
        $folderRecords = Folder::whereDescendantOf(1)->get();
        $folderRecords = $rootFolder->merge($folderRecords);
        $initialFolderId = $request->folderId();

        return View::make('folder.create', compact('folderRecords', 'initialFolderId'));

    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $parentFolderRecord = Folder::findOrFail($request->parent_id);

        $folderRecord = new Folder([
            'title' => $request->title(),
        ]);

        $folderRecord->appendTo($parentFolderRecord)->save();

        return redirect()->route('folder.edit', ['folderId' => $folderRecord->id])
            ->with('status', __('folder stored successfully !'));

    }
}
