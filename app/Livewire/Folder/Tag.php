<?php

namespace App\Livewire\Folder;

use Livewire\Component;

class Tag extends Component
{
    public $folderId;

    public function render()
    {
        return view('livewire.folder.tag');
    }
}
