<?php

namespace App\Http\Controllers\LedgerDiff;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
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
    /*    public function __invoke()
        {

            return View::make('ledgerDiff.show');
        }*/
    public function __invoke(Request $request)
    {
        $ledgerId = (int)$request->route('ledgerId');
        $diffId = (int)$request->route('diffId');

        //        dd($ledgerRecord);

        return View::make('ledgerDiff.show', ['ledgerId' => $ledgerId, 'diffId' => $diffId]);
    }
}
