<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\UpdateRequest;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

class UpdateController extends Controller
{
    public function edit(request $request): \Illuminate\Contracts\View\View
    {
        $ledgerId = (int)$request->route('ledgerId');

        $ledgerRecord = Ledger::with('define')->where('ledgers.id', $ledgerId)->firstOrFail();
        // 権限チェック
        if (auth()->user()->cannot('update', $ledgerRecord)) {
            abort(403);
        }

        return View::make('ledger.edit', [
            'ledgerDefineRecord' => $ledgerRecord->define,
            'ledger' => $ledgerRecord,
        ]);

    }

    public function delete(request $request)
    {
        $ledgerId = (int)$request->route('ledgerId');

        $ledgerRecord = Ledger::findOrFail($ledgerId);
        // 権限チェック
        if (auth()->user()->cannot('delete', $ledgerRecord)) {
            abort(403, __('ledger.not_allow_delete'));
        }
        $ledgerRecord->delete();
        session()->flash('status', __('ledger.remove_success'));

        return View::make('ledger.message', ['windowTitle' => 'ledger']);

    }

    public function destroy(Request $request, Ledger $ledger)
    {
        // 権限チェック
//        if (Gate::denies('delete', [Ledger::class, $ledger->define])) {
        if (auth()->user()->cannot('destroy', $ledger)) {
            abort(403, __('ledger.not_allow_destroy'));
        }

        $ledger->delete();

        session()->flash('status', __('ledger.remove_success'));
        return View::make('ledger.message', ['windowTitle' => 'ledger']);
    }

}
