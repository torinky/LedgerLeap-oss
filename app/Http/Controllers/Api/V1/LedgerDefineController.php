<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Resources\Api\V1\LedgerDefineResource;
use App\Models\LedgerDefine;

class LedgerDefineController extends Controller
{
    public function index()
    {
        return LedgerDefineResource::collection(LedgerDefine::all());
    }
}
