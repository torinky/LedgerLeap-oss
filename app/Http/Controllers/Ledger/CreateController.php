<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\LedgerDefine;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    public function create(createRequest $request)
    {
        $ledgeDefineRecord = LedgerDefine::findOrFail($request->ledgerDefineId);

        return View::make('ledger.create', ['ledgerDefineRecord' => $ledgeDefineRecord]);

    }
}
