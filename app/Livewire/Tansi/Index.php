<?php

namespace App\Livewire\Tansi;

use App\Models\Synonym\Tansi;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Layout('layouts.app')]
    public function render(): View
    {
        $tansis = Tansi::paginate();

        return view('livewire.tansi.index', compact('tansis'))
            ->with('i', $this->getPage() * $tansis->perPage());
    }

    public function delete(Tansi $tansi)
    {
        $tansi->delete();

        return $this->redirectRoute('tansi.index', navigate: true);
    }
}
