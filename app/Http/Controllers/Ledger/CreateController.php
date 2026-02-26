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

        // prefillパラメータを検証
        $prefillParams = $this->validatePrefillParams(
            $request->query('prefill', []),
            $ledgerDefine
        );

        // 新規台帳作成用のビューをレンダリング
        return View::make('ledger.create', [
            'ledgerDefineRecord' => $ledgerDefine,
            'prefillParams' => $prefillParams,
        ]);
    }

    /**
     * prefillパラメータを検証し、安全な値のみを返す
     */
    private function validatePrefillParams(array $params, LedgerDefine $ledgerDefine): array
    {
        $validated = [];
        $columnDefines = collect($ledgerDefine->column_define);

        foreach ($params as $columnId => $value) {
            $column = $columnDefines->firstWhere('id', (int) $columnId);

            // カラムが存在しない場合はスキップ
            if (! $column) {
                continue;
            }

            // auto_number と files は prefill 対象外
            if (in_array($column->type, ['auto_number', 'files'])) {
                continue;
            }

            // XSS対策: 文字列のサニタイズ
            if (is_string($value)) {
                $value = strip_tags($value);
                $value = mb_substr($value, 0, 5000); // 最大5000文字
            }

            // 配列の場合（chk など）
            if (is_array($value)) {
                $value = array_map(function ($item) {
                    return is_string($item) ? strip_tags(mb_substr($item, 0, 255)) : $item;
                }, $value);
            }

            // select/chk の場合、選択肢に存在するかチェック
            if (in_array($column->type, ['select', 'chk']) && ! empty($column->options)) {
                if (is_array($value)) {
                    $value = array_filter($value, fn ($v) => in_array($v, $column->options, true));
                } elseif (! in_array($value, $column->options, true)) {
                    continue;
                }
            }

            $validated[$columnId] = $value;
        }

        return $validated;
    }
}
