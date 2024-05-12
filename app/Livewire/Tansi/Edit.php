<?php

namespace App\Livewire\Tansi;

use App\Livewire\Forms\TansiForm;
use App\Models\Synonym\Tansi;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Edit extends Component
{
    public TansiForm $form;

    public function mount(Tansi $tansiV110)
    {
        $this->form->setTansiModel($tansiV110);
    }

    public function save()
    {
        $this->form->update();

        return $this->redirectRoute('tansi-v110s.index', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.tansi.edit');
    }
}
