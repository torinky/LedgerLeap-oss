<?php

namespace App\Http\Controllers\LedgerDefine;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class IndexController extends Controller
{

    /**
     * @return Application|Factory|View
     */
    public function __invoke()
    {

        return view('ledgerDefine.index');
    }
}
