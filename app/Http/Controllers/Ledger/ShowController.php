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
        $ledgerDefineRecord = null;
        if (!empty($ledger)) {
            $ledgerDefineRecord = $ledger->define;
        }

        // 権限チェック
        if (Gate::denies('view', [Ledger::class, $ledgerDefineRecord->folder])) {
            abort(403);
        }


        return View::make('ledger.show', compact('ledger', 'ledgerDefineRecord'));
    }
}
