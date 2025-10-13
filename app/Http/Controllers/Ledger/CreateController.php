<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;

class CreateController extends Controller
{
    /**
     * 新規台帳を作成するための処理を実行します。
     *
     * @param  CreateRequest  $request  リクエストオブジェクトで必要なパラメータが含まれています。
     * @return \Illuminate\Contracts\View\View|Factory 新規台帳を作成するためのビューを返します。
     *
     * @throws AuthorizationException 台帳を作成する権限がない場合にスローされます。
     * @throws ModelNotFoundException 特定の台帳定義が見つからない場合にスローされます。
     * @throws AuthorizationException 台帳定義に対応するフォルダーへの書き込み権限がない場合にスローされます。
     */
    public function create(CreateRequest $request)
    {
        // 台帳を作成する権限があるか確認
        if (! auth()->user()->can('create_ledgers')) {
            abort(403, __('ledger.not_allow_create'));
        }

        // リクエストパラメータから台帳定義を特定
        $ledgerDefine = LedgerDefine::findOrFail($request->ledgerDefineId);

        //        if (Gate::denies('create', [Ledger::class, $ledgerDefine])) {
        if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
            abort(403, __('ledger.not_allow_create'));
        }

        // 新規台帳作成用のビューをレンダリング
        return View::make('ledger.create', ['ledgerDefineRecord' => $ledgerDefine]);
    }
}
