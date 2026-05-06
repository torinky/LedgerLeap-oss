<?php

namespace App\Http\Controllers\LedgerDefine;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    public function create(CreateRequest $request): \Illuminate\Contracts\View\View
    {
        //        $this->authorize('create_ledger_defines', LedgerDefine::class);
        if (auth()->user()->cannot('create_ledger_defines', LedgerDefine::class)) {
            abort(403, __('ledger.define.not_allow_create'));
        }
        // ── パンくずリストの取得 ──────────────────────────────────────
        $breadcrumbs = [];
        if ($request->folderId()) {
//        dd($request->folderId());
            $folder = \App\Models\Folder::with('ancestors')->find($request->folderId());
            if ($folder) {
                $breadcrumbs = $folder->ancestors->all();
                $breadcrumbs[] = $folder;
            }
        }
        return View::make('ledgerDefine.create', compact('breadcrumbs'));

    }
}
