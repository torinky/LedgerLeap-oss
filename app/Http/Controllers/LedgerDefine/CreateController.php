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

        return View::make('ledgerDefine.create');

    }
}
