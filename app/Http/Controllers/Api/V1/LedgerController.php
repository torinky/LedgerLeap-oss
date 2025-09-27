<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLedgerRequest;
use App\Http\Resources\LedgerResource;
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
        return (new LedgerResource($ledger))
            ->response()
            ->setStatusCode(201);
    }
}