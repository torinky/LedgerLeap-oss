<?php

namespace App\Http\Controllers\Folder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Folder\UpdateRequest;
use App\Models\Folder;
use Illuminate\Support\Facades\View;

class UpdateController extends Controller
{
    public function edit(UpdateRequest $request): \Illuminate\Contracts\View\View
    {

        $rootFolder = Folder::root()->get();
        $folderRecords = Folder::whereDescendantOf(1)->get();
        $folderRecords = $rootFolder->merge($folderRecords);
        $initialFolderId = $request->folderId();

        $currentFolderRecord = Folder::where('id', $initialFolderId)->firstOrFail();

        $this->authorize('update', $currentFolderRecord);

        return View::make('folder.edit', compact('currentFolderRecord', 'folderRecords', 'initialFolderId'));

    }

    public function update(UpdateRequest $request)
    {
        $parentFolderRecord = Folder::findOrFail($request->parent_id);

        $folderRecord = Folder::find($request->id);
        $folderRecord->title = $request->title();
        //        $folderRecord->appendTo($parentFolderRecord)->save();
        $folderRecord->save();

        return redirect()->route('folder.edit', ['folderId' => $request->id])
            ->with('status', __('folder updated successfully !'));
    }

    public function delete(UpdateRequest $request)
    {
        $folderId = (int) $request->route('folderId');

        $folder = Folder::find($folderId);
        $folder->delete();

        session()->flash('status', 'folder deleted successfully !');

        return View::make('static.message', ['windowTitle' => 'folder setting']);

    }
}
