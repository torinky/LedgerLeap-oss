<?php

namespace App\View\Components\Ledger\form;

use Illuminate\View\Component;

class BaseInput extends Component
{
    public $columnDefine;

    public $ledgerRecord;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($columnDefine, $ledgerRecord = [])
    {
        $this->columnDefine = $columnDefine;
        $this->ledgerRecord = $ledgerRecord;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render()
    {
    }
}
