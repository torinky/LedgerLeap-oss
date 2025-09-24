<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\Api\V1\LedgerResource;
use App\Services\LedgerService;

class SearchController extends Controller
{
    public function search(SearchRequest $request, LedgerService $ledgerService)
    {
        $validatedParams = $request->validated();

        $result = $ledgerService->searchLedgersForApi($validatedParams);

        if (($validatedParams['mode'] ?? 'search') === 'count') {
            return response()->json([
                'meta' => [
                    'total' => $result['total'],
                ]
            ]);
        }

        return LedgerResource::collection($result['ledgers'])->additional([
            'meta' => [
                'total' => $result['total'],
                'limit' => (int)($validatedParams['limit'] ?? 10),
                'offset' => (int)($validatedParams['offset'] ?? 0),
            ],
        ]);
    }
}
