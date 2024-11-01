<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\UpdateRequest;
use App\Models\Ledger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class UpdateController extends Controller
{
    public function edit(request $request): \Illuminate\Contracts\View\View
    {
        //        $ledgerId = (int)$request->route('ledgerId');

        //        $ledgerRecord = Ledger::with('define')->where('ledgers.id', $ledgerId)->firstOrFail();

        //        return View::make('ledger.edit', compact('ledgerRecord'));
        return View::make('ledger.edit');

    }

    public function update(UpdateRequest $request)
    {
        $ledgerRecord = Ledger::find($request->id);
        $ledgerRecord->content = $request->content();
        $ledgerRecord->save();

        //        $ledgerRecord = Ledger::find($request->id);

        return redirect()->route('ledger.show', ['ledgerId' => $request->id])
            ->with('status', __('ledger record updated successfully !'));
        //        return View::make('static.message', ['windowTitle' => 'ledger']);
        //        return View::make('ledger.show', ['ledgerRecord' => $ledgerRecord, 'ledgerId' => $request->id])
        //            ->with('status', __('ledger record updated successfully !'));

    }

    public function delete(request $request)
    {
        $ledgerId = (int)$request->route('ledgerId');

        Ledger::find($ledgerId)->delete();
        session()->flash('status', __('ledger.remove_success'));

        return View::make('ledger.message', ['windowTitle' => 'ledger']);

    }
}
