<?php

namespace App\Livewire\Words;

use App\Models\Synonym\Word;
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
        $words = Word::paginate();

        return view('livewire.word.index', compact('words'))
            ->with('i', $this->getPage() * $words->perPage());
    }

    public function delete(Word $word)
    {
        $word->delete();

        return $this->redirectRoute('words.index', navigate: true);
    }
}
