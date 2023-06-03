<?php

namespace App\Facades;

use App\Services\JpDatetimeService;
use Illuminate\Support\Facades\Facade;

class JpDatetime extends Facade
{
    protected static function getFacadeAccessor()
    {
        return JpDatetimeService::class;
    }

}
