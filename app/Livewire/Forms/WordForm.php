<?php

namespace App\Livewire\Forms;

use App\Models\Synonym\Word;
use Livewire\Form;

class WordForm extends Form
{
    public ?Word $wordModel;

    public $wordid = '';

    public $lang = '';

    public $lemma = '';

    public $pron = '';

    public $pos = '';

    public function rules(): array
    {
        return [
            'lang' => 'string',
            'lemma' => 'string',
            'pron' => 'string',
            'pos' => 'string',
        ];
    }

    public function setWordModel(Word $wordModel): void
    {
        $this->wordModel = $wordModel;

        $this->wordid = $this->wordModel->wordid;
        $this->lang = $this->wordModel->lang;
        $this->lemma = $this->wordModel->lemma;
        $this->pron = $this->wordModel->pron;
        $this->pos = $this->wordModel->pos;
    }

    public function store(): void
    {
        $this->wordModel->create($this->validate());

        $this->reset();
    }

    public function update(): void
    {
        $this->wordModel->update($this->validate());

        $this->reset();
    }
}
