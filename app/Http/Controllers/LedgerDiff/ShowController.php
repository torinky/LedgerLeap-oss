<?php

namespace App\Http\Controllers\LedgerDiff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class ShowController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return Response
     */
    public function __invoke()
    {

        return View::make('ledgerDiff.show');
    }
    /*    public function __invoke(Request $request)
        {
            $ledger = new Ledger();
            $ledgerId = (int)$request->route('ledgerId');

            $ledgerRecord = $ledger->with('define')->where('ledgers.id', $ledgerId)->firstOrFail();
    //        dd($ledgerRecord);

            return View::make('ledger.show', compact('ledgerRecord'));
        }*/
}
