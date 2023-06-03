<?php

namespace App\Http\Controllers\LedgerDefine;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    public function create(CreateRequest $request): \Illuminate\Contracts\View\View
    {
        $rootFolder = Folder::root()->get();
        $folderRecords = Folder::whereDescendantOf(1)->get();
        $folderRecords = $rootFolder->merge($folderRecords);
        $initialFolderId = $request->folderId();

        return View::make('ledgerDefine.create', compact('folderRecords', 'initialFolderId'));

    }

    /**
     * @param CreateRequest $request
     * @return RedirectResponse
     */
    public function store(CreateRequest $request): RedirectResponse
    {
        $ledgerDefine = new LedgerDefine;
        $ledgerDefineRecord = $ledgerDefine->create([
            'title' => $request->title(),
            'folder_id' => $request->folderId(),
            'column_define' => $request->column_define(),
        ]);

        return redirect()->route('ledgerDefine.edit', ['ledgerDefineId' => $ledgerDefineRecord->id])
            ->with('status', __('ledger definition stored successfully !'));

    }

}
