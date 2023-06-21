<?php

namespace App\Http\Livewire\Folder;

use App\Models\Folder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class Tree extends Component
{
    public \Fureev\Trees\Collection|Collection $folders;

    public function mount()
    {
        $this->folders = Folder::all()->toTree();
    }

    public function render()
    {
        return view('livewire.folder.tree');
    }
}
