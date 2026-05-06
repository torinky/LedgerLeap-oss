<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Http\Requests\LedgerDefine\CreateRequest;
use App\Models\Ledger;
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
        $ledgerDefine = $this->findLedgerDefineOrFail($request->ledgerDefineId);

        $this->authorizeLedgerCreate($ledgerDefine);

        // prefillパラメータを検証
        $prefillParams = $this->validatePrefillParams(
            $request->query('prefill', []),
            $ledgerDefine
        );

        // ── パンくずリストの取得 ──────────────────────────────────────
        $breadcrumbs = [];
        if ($ledgerDefine && $ledgerDefine->folder_id) {
            $folder = \App\Models\Folder::with('ancestors')->find($ledgerDefine->folder_id);
            if ($folder) {
                $breadcrumbs = $folder->ancestors->all();
                $breadcrumbs[] = $folder;
            }
        }

        // 新規台帳作成用のビューをレンダリング
        return View::make('ledger.create', [
            'ledgerDefineRecord' => $ledgerDefine,
            'prefillParams' => $prefillParams,
            'breadcrumbs' => $breadcrumbs,
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
            /** @var object|null $column */
            $column = $columnDefines->firstWhere('id', (int) $columnId);

            // カラムが存在しない場合はスキップ
            if (! $column) {
                continue;
            }

            // auto_number と files は prefill 対象外
            if (in_array($column->type, ['auto_number', 'files'])) {
                continue;
            }

            if ($column->type === 'chk') {
                $value = $this->normalizeCheckboxPrefillValue($value, $column->options ?? []);

                if ($value === []) {
                    continue;
                }

                $validated[$columnId] = $value;

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

    private function findLedgerDefineOrFail(int|string $ledgerDefineId): LedgerDefine
    {
        $ledgerDefine = LedgerDefine::query()->whereKey($ledgerDefineId)->first();

        if (! $ledgerDefine instanceof LedgerDefine) {
            throw (new ModelNotFoundException)->setModel(LedgerDefine::class, [$ledgerDefineId]);
        }

        return $ledgerDefine;
    }

    private function authorizeLedgerCreate(LedgerDefine $ledgerDefine): void
    {
        if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
            abort(403, __('ledger.not_allow_create'));
        }
    }

    private function normalizeCheckboxPrefillValue(mixed $value, array $options): array
    {
        $normalized = [];

        if (is_string($value)) {
            $sanitizedOption = $this->sanitizeCheckboxOption($value);

            if ($sanitizedOption !== null && ($options === [] || in_array($sanitizedOption, $options, true))) {
                $normalized[$sanitizedOption] = true;
            }

            return $normalized;
        }

        if (! is_array($value)) {
            return $normalized;
        }

        if (array_is_list($value)) {
            foreach ($value as $option) {
                $sanitizedOption = $this->sanitizeCheckboxOption($option);

                if ($sanitizedOption === null) {
                    continue;
                }

                if ($options !== [] && ! in_array($sanitizedOption, $options, true)) {
                    continue;
                }

                $normalized[$sanitizedOption] = true;
            }

            return $normalized;
        }

        foreach ($value as $option => $selected) {
            $sanitizedOption = $this->sanitizeCheckboxOption($option);

            if ($sanitizedOption === null) {
                continue;
            }

            if ($options !== [] && ! in_array($sanitizedOption, $options, true)) {
                continue;
            }

            if (! $this->isTruthyCheckboxSelection($selected)) {
                continue;
            }

            $normalized[$sanitizedOption] = true;
        }

        return $normalized;
    }

    private function sanitizeCheckboxOption(mixed $option): ?string
    {
        if (! is_string($option)) {
            return null;
        }

        $sanitizedOption = trim(strip_tags(mb_substr($option, 0, 255)));

        return $sanitizedOption === '' ? null : $sanitizedOption;
    }

    private function isTruthyCheckboxSelection(mixed $selected): bool
    {
        if (is_bool($selected)) {
            return $selected;
        }

        if (is_int($selected) || is_float($selected)) {
            return (int) $selected === 1;
        }

        if (! is_string($selected)) {
            return false;
        }

        return in_array(strtolower(trim($selected)), ['1', 'true', 'on', 'yes'], true);
    }
}
