<?php

namespace App\Livewire\Folder;

class Tag extends BaseLivewireComponent
{
    public $folderId;

    public function render()
    {
        return view('livewire.folder.tag');
    }
}
