<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLedgerRequest;
use App\Http\Resources\Api\V1\LedgerResource;
use App\Models\Folder;
use App\Services\LedgerService;
use App\Services\UserService;
use Illuminate\Http\Request;

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
     * @OA\Post(
     *     path="/api/v1/ledgers",
     *     summary="Create a new ledger",
     *     description="Creates a new ledger record based on a ledger definition.",
     *     tags={"Ledgers"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Ledger object that needs to be added to the store",
     *         @OA\JsonContent(ref="#/components/schemas/StoreLedgerRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully created",
     *         @OA\JsonContent(ref="#/components/schemas/LedgerResource")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreLedgerRequest $request)
    {
        // フォルダの存在確認と取得
        $folder = Folder::findOrFail($request->validated('folder_id'));

        // 認可チェック: ユーザーがこのフォルダに書き込む権限を持っているか
        if (!$this->userService->isWritableFolderForUser($request->user(), $folder)) {
            abort(403);
        }

        // サービスの呼び出し
        $ledger = $this->ledgerService->createLedger($request->validated());

        // レスポンスの返却
        return (new LedgerResource($ledger->load(['define', 'define.folder', 'define.folder.ancestors', 'define.tags'])))
            ->response()
            ->setStatusCode(201);
    }
}