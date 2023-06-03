<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

//use App\Models\Ledger;

class IndexController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @return Response
     */
//    public function __invoke(Request $request, LedgerService $ledgerService)


    public function __invoke(Request $request)
    {
//        $ledgers = Ledger::all();
//        $ledgers = $ledgerService->getLedgers();
//        dd($ledgers);
//        return view('ledger.index')->with(compact('ledgers'));
        return view('ledger.index');
    }
}
