<?php

namespace App\Livewire\Words;

use App\Livewire\Forms\WordForm;
use App\Models\Synonym\Word;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Create extends Component
{
    public WordForm $form;

    public function mount(Word $word)
    {
        $this->form->setWordModel($word);
    }

    public function save()
    {
        $this->form->store();

        return $this->redirectRoute('words.index', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.word.create');
    }
}
