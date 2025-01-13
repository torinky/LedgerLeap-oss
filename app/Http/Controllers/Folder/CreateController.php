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
        /*        $rootFolder = Folder::root()->get();
                $folderRecords = Folder::whereDescendantOf(1)->get();
                $folderRecords = $rootFolder->merge($folderRecords);
                $initialFolderId = $request->folderId();*/

        $currentFolder = Folder::find($request->folderId());
//        dd($currentFolder);
//        if (auth()->user()->cannot('create', $currentFolder)) {
        if (auth()->user()->cannot('create', $currentFolder)) {
            abort(403, __('ledger.folder.not_allow_create'));
        }

//        return View::make('folder.create', compact('folderRecords', 'initialFolderId'));
        return View::make('folder.create');

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
