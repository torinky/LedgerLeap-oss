<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\SearchRequest;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SearchController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke(SearchRequest $request, LedgerService $ledgerService)
    {
        if ($request->keyword()) {
            $ledgers = $ledgerService->searchLedgers($request->keyword());
        } else {
            $ledgers = $ledgerService->getLedgers();
        }

        // dd($ledgers);

        return view('ledger.index')
            ->with(compact('ledgers'));
    }
}
