<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\Api\V1\LedgerResource;
use App\Services\LedgerService;

class SearchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/search",
     *     summary="Search ledgers",
     *     description="Advanced search for ledgers based on various criteria.",
     *     tags={"Search"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="q", description="Full-text search keyword.", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="tags", description="Comma-separated tag names to filter by (AND condition).", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="folder_id", description="Search recursively within the specified folder ID.", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="ledger_define_id", description="Filter by a specific ledger definition ID.", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="exclude_q", description="Keyword to exclude from results.", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="exclude_tags", description="Comma-separated tag names to exclude.", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="mode", description="Response mode ('search' or 'count').", in="query", required=false, @OA\Schema(type="string", enum={"search", "count"}, default="search")),
     *     @OA\Parameter(name="limit", description="Maximum number of items to return.", in="query", required=false, @OA\Schema(type="integer", default=10)),
     *     @OA\Parameter(name="offset", description="Number of items to skip for pagination.", in="query", required=false, @OA\Schema(type="integer", default=0)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LedgerResource")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="limit", type="integer"),
     *                 @OA\Property(property="offset", type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation Error")
     * )
     */
    public function search(SearchRequest $request, LedgerService $ledgerService)
    {
        $validatedParams = $request->validated();

        $result = $ledgerService->searchLedgersForApi($request->user(), $validatedParams);

        if (($validatedParams['mode'] ?? 'search') === 'count') {
            return response()->json([
                'meta' => [
                    'total' => $result['total'],
                ],
            ]);
        }

        return LedgerResource::collection($result['ledgers'])->additional([
            'meta' => [
                'total' => $result['total'],
                'limit' => (int) ($validatedParams['limit'] ?? 10),
                'offset' => (int) ($validatedParams['offset'] ?? 0),
            ],
        ]);
    }
}
