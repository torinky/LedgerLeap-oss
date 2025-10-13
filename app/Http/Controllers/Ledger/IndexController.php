<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// use App\Models\Ledger;

class IndexController extends Controller
{
    /**
     * Handle the incoming request.
     *
     * @return Response
     */
    //    public function __invoke(Request $request, LedgerService $ledgerService)

    public function __invoke(Request $request)
    {
        /*        $tikaClient = \Vaites\ApacheTika\Client::make('tika', 9998);
                $language = $tikaClient->getLanguage('/var/www/html/storage/app/livewire-tmp/0VwWIMQKqnoNxwoW1DLTmPc9q5Vdhu-metaQXJhZ2FraVl1aV8xNmNoYV8xNzAyXzAyNy5qcGc=-.jpg');
                $metadata = $tikaClient->getMetadata('/var/www/html/storage/app/livewire-tmp/0VwWIMQKqnoNxwoW1DLTmPc9q5Vdhu-metaQXJhZ2FraVl1aV8xNmNoYV8xNzAyXzAyNy5qcGc=-.jpg');
                dd($language,$metadata);*/

        //        $ledgers = Ledger::all();
        //        $ledgers = $ledgerService->getLedgers();
        //        dd($ledgers);
        //        return view('ledger.index')->with(compact('ledgers'));
        return view('ledger.index');
    }
}
