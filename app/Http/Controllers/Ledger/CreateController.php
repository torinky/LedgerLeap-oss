<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\LedgerDefine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    /**
     * 新規台帳を作成するための処理を実行します。
     *
     * @param CreateRequest $request リクエストオブジェクトで必要なパラメータが含まれています。
     * @return \Illuminate\Contracts\View\View|Factory 新規台帳を作成するためのビューを返します。
     * @throws AuthorizationException 台帳を作成する権限がない場合にスローされます。
     * @throws ModelNotFoundException 特定の台帳定義が見つからない場合にスローされます。
     * @throws AuthorizationException 台帳定義に対応するフォルダーへの書き込み権限がない場合にスローされます。
     */
    public function create(CreateRequest $request)
    {
        // 台帳を作成する権限があるか確認
        if (!auth()->user()->can('create_ledgers')) {
            abort(403, __('ledger.not_allow_create'));
        }

        // リクエストパラメータから台帳定義を特定
        $ledgerDefine = LedgerDefine::findOrFail($request->ledgerDefineId);

        // 認証済みユーザーのすべてのユニークなロールを取得
        $userRoles = auth()->user()->getAllUniqueRoles();

        // ユーザーのロールに基づき、書き込み可能なフォルダーのIDを取得
        $writableFolderIds = $userRoles->flatMap(fn($role) => $role->writableFolders()->pluck('id'));

        // 台帳定義に対応するフォルダーを取得
        $ledgerFolder = $ledgerDefine->folder;

        // 台帳定義に対応するフォルダーの先祖フォルダーIDを取得
        $ancestorFolderIds = $ledgerFolder->ancestorsAndSelf($ledgerFolder->id)->pluck('id');

        // ユーザーが台帳定義に対応するフォルダーに書き込み権限があるか確認
        if ($writableFolderIds->intersect($ancestorFolderIds)->isEmpty()) {
            abort(403, __('ledger.not_allow_write_folder'));
        }

        // 新規台帳作成用のビューをレンダリング
        return View::make('ledger.create', ['ledgerDefineRecord' => $ledgerDefine]);
    }
}
