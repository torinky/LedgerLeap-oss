<?php

namespace App\Facades\Ledger;

use Illuminate\Support\Facades\Facade;

class ColumnHtml extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \App\Services\Ledger\ColumnHtml::class;
    }
}
