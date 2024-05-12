<?php

namespace App\Livewire\Forms;

use App\Models\Synonym\Tansi;
use Livewire\Form;

class TansiForm extends Form
{
    public ?Tansi $tansiModel;

    public $WORD = '';

    public $pronunciation1 = '';

    public $pronunciation2 = '';

    public $category1 = '';

    public $category2 = '';

    public $CANDIDATES = '';

    public function rules(): array
    {
        return [
            'WORD' => 'string',
            'pronunciation1' => 'string',
            'pronunciation2' => 'string',
            'category1' => 'string',
            'category2' => 'string',
            'CANDIDATES' => 'string',
        ];
    }

    public function setTansiModel(Tansi $tansiModel): void
    {
        $this->tansiModel = $tansiModel;

        $this->WORD = $this->tansiModel->WORD;
        $this->pronunciation1 = $this->tansiModel->pronunciation1;
        $this->pronunciation2 = $this->tansiModel->pronunciation2;
        $this->category1 = $this->tansiModel->category1;
        $this->category2 = $this->tansiModel->category2;
        $this->CANDIDATES = $this->tansiModel->CANDIDATES;
    }

    public function store(): void
    {
        $this->tansiModel->create($this->validate());

        $this->reset();
    }

    public function update(): void
    {
        $this->tansiModel->update($this->validate());

        $this->reset();
    }
}
