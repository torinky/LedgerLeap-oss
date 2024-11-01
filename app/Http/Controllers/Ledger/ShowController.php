<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Services\LedgerService;
use Illuminate\Http\Request;
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
        $ledger = new Ledger;
        $ledgerId = (int)$request->route('ledgerId');

        $ledgerRecord = $ledger->with(['define', 'modifier'])->withCount('ledgerDiff')->where('ledgers.id', $ledgerId)->firstOrFail();
        //        dd($ledgerRecord);

        return View::make('ledger.show', compact('ledgerRecord'));
    }
}
