<?php

namespace App\Livewire\Tansi;

use App\Livewire\Forms\TansiForm;
use App\Models\Synonym\Tansi;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public TansiForm $form;

    public function mount(Tansi $tansiV110)
    {
        $this->form->setTansiModel($tansiV110);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.tansi.show', ['tansi' => $this->form->tansiModel]);
    }
}
