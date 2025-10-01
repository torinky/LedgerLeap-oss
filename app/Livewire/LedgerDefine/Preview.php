<?php

namespace App\Livewire\LedgerDefine;

use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;

class Preview extends Component
{
    public $ledgerDefineRecord;

    public int $ledgerDefineId;

    public $backgroundImages = [];

    public $descriptionGroup = 'createDescription';

    public function mount(request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $this->ledgerDefineId = (int) $request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $this->ledgerDefineId)->firstOrNew();
        $this->initBackgroundImages();

    }

    #[On('toggleDescriptionGroup')]
    public function toggleDescriptionGroup($name)
    {
        $this->descriptionGroup = $name;
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
        /*        Log::debug('LedgerDefine Preview: create_description', ['content' => $this->ledgerDefineRecord->create_description]);
                Log::debug('LedgerDefine Preview: list_description', ['content' => $this->ledgerDefineRecord->list_description]);
                Log::debug('LedgerDefine Preview: detail_description', ['content' => $this->ledgerDefineRecord->detail_description]);*/

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
                if (empty($value['path'])) {
                    return null;
                }

                return asset('storage/'.$value['path']);
            })->toArray();
    }
}
