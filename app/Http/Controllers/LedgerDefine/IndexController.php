<?php

namespace App\Http\Controllers\LedgerDefine;

use App\Http\Controllers\Controller;
use App\Models\LedgerDefine;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;

class IndexController extends Controller
{
    /**
     * @return Application|Factory|View
     */
    public function index()
    {
        $this->authorize('view_ledger_defines', LedgerDefine::class);

        return view('ledgerDefine.index');
    }
}
