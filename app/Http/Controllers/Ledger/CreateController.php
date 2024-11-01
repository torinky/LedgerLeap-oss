<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    public function create()
    {
        return View::make('ledger.create');

    }
}
