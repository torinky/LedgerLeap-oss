<?php

namespace App\Livewire\LedgerDefine;

use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Livewire\Attributes\On;
use Livewire\Component;

class Preview extends Component
{
    public $ledgerDefineRecord;

    public int $ledgerDefineId;

    public $backgroundImages = [];

    public $descriptionGroup = "createDescription";

    public function mount(request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $this->ledgerDefineId = (int)$request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $this->ledgerDefineId)->firstOrNew();
        $this->initBackgroundImages();

    }

    #[On('ledgerDefineRecordStored')]
    public function redraw()
    {
        //        dd('redrawing!');
        $ledgerDefine = new LedgerDefine;
        $this->ledgerDefineRecord = $ledgerDefine->where('id', $this->ledgerDefineId)->firstOrNew();
        //        session()->flash('status', __('ledger.define.saved'));
        $this->render();
    }

    public function render()
    {
        return view('livewire.ledger-define.preview');
    }

    #[On('applyBackgroundImages')]
    public function applyBackgroundImages($files)
    {
        $this->backgroundImages = $files;
        //        dd($this->backgroundImages);
        //        $this->render();
    }

    private function initBackgroundImages()
    {
        $this->backgroundImages = collect($this->ledgerDefineRecord->column_define)->pluck('file', 'id')
            ->map(function ($value) {
                if (empty($value->path)) {
                    return null;
                }

                return asset('storage/' . $value->path);
            })->toArray();
    }
}
