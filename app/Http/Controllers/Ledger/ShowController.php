<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

class ShowController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function __invoke(Request $request, LedgerService $ledgerService)
    {
        $ledgerId = (int)$request->route('ledgerId');
        $ledger = Ledger::with(['define'])->findOrFail($ledgerId);

        $this->authorize('view', $ledger);

        $ledgerDefineRecord = null;
        if (!empty($ledger)) {
            $ledgerDefineRecord = $ledger->define;
        }

        //表示可否の判定はlivewireで行う
        if (empty($ledger)) {
            abort(404, __('ledger.not_found'));
        }


        return View::make('ledger.show', compact('ledger', 'ledgerDefineRecord'));
    }
}
