<?php

namespace App\Livewire\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use Illuminate\Http\Request;
use Livewire\Component;

class ShowDiff extends Component
{
    public $ledgerRecord;

    public $ledgerId;

    public $offset = 0;

    public $nowLedgerRecord;

    public $ledgerDiffCount = 0;

    public function mount(Request $request)
    {
        $this->ledgerId = (int)$request->route('ledgerId');

        $this->nowLedgerRecord = Ledger::with(['define', 'modifier'])
            ->withCount('ledgerDiff')
            ->where('ledgers.id', $this->ledgerId)
            ->firstOrFail();
        $this->ledgerRecord = $this->nowLedgerRecord;
        //リレーション先の値はlivewireではフロントエンドに伝わらないので個別にpublicに切り出す
        $this->ledgerDiffCount = $this->ledgerRecord->ledger_diff_count;
        //        dd($ledgerRecord);

    }

    public function render()
    {
        return view('livewire.ledger.show-diff');
    }

    public function changeOffset($newOffset = 0): void
    {
        if ($newOffset > $this->ledgerDiffCount) {
            $this->offset = $this->ledgerDiffCount;
        } else {
            $this->offset = $newOffset;
        }

        if ($this->offset == 0) {
            $this->ledgerRecord = Ledger::with(['define', 'modifier'])
                ->where('ledgers.id', $this->ledgerId)
                ->withCount('ledgerDiff')->firstOrFail();

        } else {
            $ledgerDiffRecord = LedgerDiff::with('modifier')->where('ledger_id', $this->ledgerId)
                ->orderBy('id', 'desc')->limit(1)->offset($this->offset - 1)->firstOrFail();
            $this->ledgerRecord->content = $ledgerDiffRecord->content;
            $this->ledgerRecord->modifier = $ledgerDiffRecord->modifier;
            $this->ledgerRecord->updated_at = $ledgerDiffRecord->updated_at;
            $this->ledgerRecord->define->column_define = $ledgerDiffRecord->column_define;
        }
    }
}
