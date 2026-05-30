<?php

namespace App\Facades\Ledger;

use App\Services\Ledger\ColumnHtmlService;
use Illuminate\Support\Facades\Facade;

class ColumnHtml extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ColumnHtmlService::class;
    }
}
