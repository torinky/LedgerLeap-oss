<?php

namespace App\Http\Controllers\LedgerDefine;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\Folder;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class UpdateController extends Controller
{
    public function edit(Request $request): \Illuminate\Contracts\View\View
    {
        $ledgerDefine = new LedgerDefine;
        $ledgerDefineId = (int) $request->route('ledgerDefineId');

        $ledgerDefineRecord = $ledgerDefine->where('id', $ledgerDefineId)->firstOrFail();

        $this->authorize('update', $ledgerDefineRecord);

        $rootFolder = Folder::root()->get();
        $folderRecords = Folder::whereDescendantOf($rootFolder->pluck('id')[0])->get();
        $folderRecords = $rootFolder->merge($folderRecords);

        // ── パンくずリストの取得 ──────────────────────────────────────
        $breadcrumbs = [];
        if ($ledgerDefineRecord && $ledgerDefineRecord->folder_id) {
            $folder = Folder::with('ancestors')->find($ledgerDefineRecord->folder_id);
            if ($folder) {
                $breadcrumbs = $folder->ancestors->all();
                $breadcrumbs[] = $folder;
            }
        }

        return View::make('ledgerDefine.edit', compact('ledgerDefineRecord', 'folderRecords', 'breadcrumbs'));

    }

    public function update(CreateRequest $request)
    {
        $ledgerDefineRecord = LedgerDefine::find($request->id);
        $ledgerDefineRecord->title = $request->title();
        $ledgerDefineRecord->column_define = $request->column_define();
        $ledgerDefineRecord->folder_id = $request->folderId();
        $ledgerDefineRecord->save();

        return redirect()->route('ledgerDefine.edit', ['tenant' => tenant()->id, 'ledgerDefineId' => $request->id])
            ->with('status', __('ledger define updated successfully !'));

    }

    public function delete(Request $request)
    {
        $ledgerDefineId = (int) $request->route('ledgerDefineId');

        $ledgerDefine = LedgerDefine::find($ledgerDefineId);

        $ledgerDefine->ledgers()->delete();
        $ledgerDefine->delete();
        session()->flash('status', 'ledger define deleted successfully !');

        return View::make('ledger.message', ['windowTitle' => 'ledger setting']);

    }
}
