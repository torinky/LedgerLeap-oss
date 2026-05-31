<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\Api\V1\LedgerResource;
use App\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Annotations as OA;

/**
 * REST search endpoint for `GET` / `POST` `/api/v1/search`.
 *
 * Keeps the HTTP search contract separate from the MCP search tool while
 * applying the authenticated user's readable-folder scope.
 */
class SearchController extends Controller
{
    /**
     * Search ledgers for API consumers.
     *
     * @param  SearchRequest  $request  Validated search parameters
     * @param  LedgerService  $ledgerService  Service that performs ledger search logic
     * @return AnonymousResourceCollection|JsonResponse
     *
     * @OA\Get(
     *     path="/api/v1/search",
     *     summary="Search ledgers (GET)",
     *     description="Advanced search for ledgers based on various criteria. For Japanese or multi-byte characters, consider using POST /api/v1/search or ensure proper URL encoding.",
     *     tags={"Search"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="q", description="Full-text search keyword. For Japanese text, use POST method or ensure URL encoding.", in="query", required=false, @OA\Schema(type="string")),
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
     *
     * @OA\Post(
     *     path="/api/v1/search",
     *     summary="Search ledgers (POST)",
     *     description="Advanced search for ledgers based on various criteria. Recommended for Japanese or multi-byte character queries as it avoids URL encoding issues.",
     *     tags={"Search"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="q", type="string", description="Full-text search keyword. Supports Japanese without URL encoding (e.g., '株式会社')."),
     *             @OA\Property(property="tags", type="string", description="Comma-separated tag names to filter by (AND condition)."),
     *             @OA\Property(property="folder_id", type="integer", description="Search recursively within the specified folder ID."),
     *             @OA\Property(property="ledger_define_id", type="integer", description="Filter by a specific ledger definition ID."),
     *             @OA\Property(property="exclude_q", type="string", description="Keyword to exclude from results."),
     *             @OA\Property(property="exclude_tags", type="string", description="Comma-separated tag names to exclude."),
     *             @OA\Property(property="mode", type="string", enum={"search", "count"}, default="search", description="Response mode."),
     *             @OA\Property(property="limit", type="integer", default=10, description="Maximum number of items to return."),
     *             @OA\Property(property="offset", type="integer", default=0, description="Number of items to skip for pagination."),
     *         )
     *     ),
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
        if (config('app.debug')) {
            \Log::info('[MCP Search Debug] === SearchController::search called ===');
            \Log::info('[MCP Search Debug] Request URL: '.$request->fullUrl());
            \Log::info('[MCP Search Debug] Request method: '.$request->method());
            \Log::info('[MCP Search Debug] Request Headers: '.json_encode($request->headers->all()));
        }

        $validatedParams = $request->validated();

        if (config('app.debug')) {
            \Log::info('[MCP Search Debug] Validated params: '.json_encode($validatedParams, JSON_UNESCAPED_UNICODE));
        }

        try {
            $startTime = microtime(true);
            $result = $ledgerService->searchLedgersForApi($request->user(), $validatedParams);
            $totalTime = microtime(true) - $startTime;

            if (config('app.debug')) {
                \Log::info('[MCP Search Debug] Total service time: '.round($totalTime * 1000, 2).'ms');
            }
        } catch (\Exception $e) {
            \Log::error('[MCP Search Debug] Exception occurred: '.$e->getMessage());
            \Log::error('[MCP Search Debug] Stack trace: '.$e->getTraceAsString());
            throw $e;
        }

        if (($validatedParams['mode'] ?? 'search') === 'count') {
            if (config('app.debug')) {
                \Log::info('[MCP Search Debug] Returning count mode response');
            }

            return response()->json([
                'meta' => [
                    'total' => $result['total'],
                ],
            ]);
        }

        if (config('app.debug')) {
            \Log::info('[MCP Search Debug] Building resource collection...');
        }
        $resourceStartTime = microtime(true);
        $response = LedgerResource::collection($result['ledgers'])->additional([
            'meta' => [
                'total' => $result['total'],
                'limit' => (int) ($validatedParams['limit'] ?? 10),
                'offset' => (int) ($validatedParams['offset'] ?? 0),
            ],
        ]);
        $resourceTime = microtime(true) - $resourceStartTime;

        if (config('app.debug')) {
            \Log::info('[MCP Search Debug] Resource collection built in: '.round($resourceTime * 1000, 2).'ms');
            \Log::info('[MCP Search Debug] === SearchController::search completed ===');
        }

        return $response;
    }
}
