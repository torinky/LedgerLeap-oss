<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Mary\Traits\Toast;

class Show extends Component
{
    use Toast;

    public Ledger|Model $ledgerRecord;
    public $canView = false;

    public function mount(Request $request)
    {
        $ledger = new Ledger;
        $ledgerId = (int)$request->route('ledgerId');

        $this->ledgerRecord = $ledger->with(['define', 'modifier'])->withCount('ledgerDiff')->where('ledgers.id', $ledgerId)->firstOrFail();
        //        dd($ledgerRecord);
        // 権限チェックはせず画面内のカラムを伏せる
        $this->canView = Gate::allows('view', [Ledger::class, $this->ledgerRecord->define]);
    }

    public function render()
    {
        return view('livewire.ledger.show');
    }
}
