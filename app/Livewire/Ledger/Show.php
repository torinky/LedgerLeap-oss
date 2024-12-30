<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use Toast;

    public Ledger|Model $ledgerRecord;

    public function mount(Request $request)
    {
        $ledger = new Ledger;
        $ledgerId = (int)$request->route('ledgerId');

        $this->ledgerRecord = $ledger->with(['define', 'modifier'])->withCount('ledgerDiff')->where('ledgers.id', $ledgerId)->firstOrFail();
        //        dd($ledgerRecord);
    }

    public function render()
    {
        return view('livewire.ledger.show');
    }
}
