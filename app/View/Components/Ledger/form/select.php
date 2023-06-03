<?php

namespace App\View\Components\Ledger\form;

use Closure;
use Illuminate\Contracts\View\View;

class select extends BaseInput
{

    /**
     * Get the view / contents that represent the component.
     *
     * @return View|Closure|string
     */
    public function render()
    {
        return view('components.ledger.form.select');
    }
}
