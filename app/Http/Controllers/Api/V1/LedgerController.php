<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Requests\Api\V1\StoreLedgerRequest;
use App\Http\Requests\Api\V1\UpdateLedgerRequest;
use App\Http\Resources\Api\V1\LedgerDetailResource;
use App\Http\Resources\Api\V1\LedgerResource;
use App\Models\Folder;
use App\Models\Ledger;
use App\Services\LedgerService;
use App\Services\UserService;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class LedgerController extends Controller
{
    protected LedgerService $ledgerService;

    protected UserService $userService;

    public function __construct(LedgerService $ledgerService, UserService $userService)
    {
        $this->ledgerService = $ledgerService;
        $this->userService = $userService;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/ledgers",
     *     summary="List and search ledgers",
     *     description="Retrieve a list of ledgers with optional filtering and search capabilities.",
     *     tags={"Ledgers"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Full-text search keyword using Mroonga",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="日報")
     *     ),
     *
     *     @OA\Parameter(
     *         name="creator_id",
     *         in="query",
     *         description="Filter by creator user ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="created_from",
     *         in="query",
     *         description="Filter by creation date from (YYYY-MM-DD format)",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-01-18")
     *     ),
     *
     *     @OA\Parameter(
     *         name="created_to",
     *         in="query",
     *         description="Filter by creation date to (YYYY-MM-DD format)",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-01-19")
     *     ),
     *
     *     @OA\Parameter(
     *         name="created_between",
     *         in="query",
     *         description="Filter by creation date range (comma-separated: from,to)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="2025-01-18,2025-01-19")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[creator_id]",
     *         in="query",
     *         description="Alternative filter syntax for creator ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter[created_between]",
     *         in="query",
     *         description="Alternative filter syntax for date range",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="2025-01-18,2025-01-19")
     *     ),
     *
     *     @OA\Parameter(
     *         name="tags",
     *         in="query",
     *         description="Comma-separated tag names to filter by",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="日報,営業")
     *     ),
     *
     *     @OA\Parameter(
     *         name="ledger_define_id",
     *         in="query",
     *         description="Filter by ledger definition ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *
     *     @OA\Parameter(
     *         name="folder_id",
     *         in="query",
     *         description="Filter by folder ID (recursive search)",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="ledgers",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/LedgerResource")
     *             ),
     *
     *             @OA\Property(property="total", type="integer", example=5)
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(SearchRequest $request)
    {
        $validated = $request->validated();

        // 'filter' パラメータがある場合は、フラット化して処理
        if (isset($validated['filter'])) {
            foreach ($validated['filter'] as $key => $value) {
                $validated[$key] = $value;
            }
            unset($validated['filter']);
        }

        // 'created_between' の特別処理
        if (isset($validated['created_between'])) {
            $dates = explode(',', $validated['created_between']);
            if (count($dates) === 2) {
                $validated['created_from'] = trim($dates[0]);
                $validated['created_to'] = trim($dates[1]);
            }
            unset($validated['created_between']);
        }

        $results = $this->ledgerService->searchLedgersForApi(
            user: $request->user(),
            params: $validated
        );

        return response()->json([
            'ledgers' => LedgerResource::collection($results['ledgers']),
            'total' => $results['total'],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/ledgers/{ledger}",
     *     summary="Get a single ledger for update confirmation",
     *     description="Retrieve a single ledger with status and column definition details before applying an update.",
     *     tags={"Ledgers"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="ledger",
     *         in="path",
     *         required=true,
     *         description="Ledger ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LedgerDetailResource")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, Ledger $ledger)
    {
        $ledger = $this->ledgerService->getLedgerForApi($ledger);
        $folder = $ledger->define?->folder;

        if (! $folder || ! $this->userService->isReadableFolderForUser($request->user(), $folder)) {
            abort(403);
        }

        return new LedgerDetailResource($ledger);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/ledgers",
     *     summary="Create a new ledger",
     *     description="Creates a new ledger record based on a ledger definition.",
     *     tags={"Ledgers"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Ledger object that needs to be added to the store",
     *
     *         @OA\JsonContent(ref="#/components/schemas/StoreLedgerRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Successfully created",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LedgerResource")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreLedgerRequest $request)
    {
        // フォルダの存在確認と取得
        /** @var Folder $folder */
        $folder = Folder::query()->findOrFail($request->validated('folder_id'));

        // 認可チェック: ユーザーがこのフォルダに書き込む権限を持っているか
        if (! $this->userService->isWritableFolderForUser($request->user(), $folder)) {
            abort(403);
        }

        // サービスの呼び出し
        $ledger = $this->ledgerService->createLedger($request->validated());

        // レスポンスの返却
        return (new LedgerResource(
            $ledger->load(['define', 'define.folder', 'define.folder.ancestors', 'define.tags'])
        ))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/ledgers/{ledger}",
     *     summary="Partially update a ledger",
     *     description="Apply a partial update to a ledger after confirming its latest status and current content.",
     *     tags={"Ledgers"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="ledger",
     *         in="path",
     *         required=true,
     *         description="Ledger ID",
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Ledger patch payload",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UpdateLedgerRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LedgerDetailResource")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=409, description="Locked by workflow status"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(UpdateLedgerRequest $request, Ledger $ledger)
    {
        $ledger = $this->ledgerService->getLedgerForApi($ledger);
        $folder = $ledger->define?->folder;

        if (! $folder || ! $this->userService->isWritableFolderForUser($request->user(), $folder)) {
            abort(403);
        }

        if ($ledger->isLocked()) {
            return response()->json([
                'message' => 'This ledger is approved and cannot be updated via the initial REST update contract.',
            ], 409);
        }

        $previousStatus = $ledger->status?->value;
        $updatedLedger = $this->ledgerService->updateLedgerForApi(
            user: $request->user(),
            ledger: $ledger,
            data: $request->validated(),
        );

        return (new LedgerDetailResource($updatedLedger))
            ->additional([
                'meta' => [
                    'previous_status' => $previousStatus,
                    'current_status' => $updatedLedger->status?->value,
                    'status_changed' => $previousStatus !== $updatedLedger->status?->value,
                    'returned_to_draft' => in_array($previousStatus, ['pending_inspection', 'pending_approval'], true)
                        && $updatedLedger->status?->value === 'draft',
                ],
            ]);
    }
}
