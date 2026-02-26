<?php

namespace App\Livewire\LedgerDefine;

use App\Livewire\BaseLivewireComponent;
use App\Livewire\Traits\HandlesFormGroups;
use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Livewire\Attributes\On;

class Preview extends BaseLivewireComponent
{
    use HandlesFormGroups;

    public $ledgerDefineRecord;

    public int $ledgerDefineId;

    public $backgroundImages = [];

    /** @var array プレビュー用のダミーcontentプロパティ（isDemo=trueで使用） */
    public array $content = [];

    public $descriptionGroup = 'createDescription';

    public function mount(Request $request)
    {
        $ledgerDefine = new LedgerDefine;
        $this->ledgerDefineId = $this->ledgerDefineId ?? (int) $request->route('ledgerDefineId');

        $this->ledgerDefineRecord = $ledgerDefine->where('id', $this->ledgerDefineId)->firstOrNew();
        $this->initBackgroundImages();
        $this->initializeGroups();
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
        $this->initializeGroups();
        $this->render();
    }

    public function render()
    {
        $groupedColumns = collect($this->ledgerDefineRecord->column_define ?? [])
            ->groupBy(fn ($column) => $column->group ?? __('ledger.form.group_default'));

        return view('livewire.ledger-define.preview', [
            'groupedColumns' => $groupedColumns,
        ]);
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
