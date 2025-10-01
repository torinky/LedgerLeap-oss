<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LedgerDefineResource;
use App\Models\LedgerDefine;

class LedgerDefineController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/ledger-defines",
     *     summary="Get a list of ledger definitions",
     *     description="Returns a list of all available ledger definitions that can be used to create new ledgers.",
     *     tags={"Ledger Defines"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LedgerDefineResource"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        return LedgerDefineResource::collection(LedgerDefine::all());
    }
}
