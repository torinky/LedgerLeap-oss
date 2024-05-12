<?php

namespace App\Livewire\Words;

use App\Livewire\Forms\WordForm;
use App\Models\Synonym\Word;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Show extends Component
{
    public WordForm $form;

    public function mount(Word $word)
    {
        $this->form->setWordModel($word);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.word.show', ['word' => $this->form->wordModel]);
    }
}
