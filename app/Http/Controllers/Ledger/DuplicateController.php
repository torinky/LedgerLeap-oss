<?php

namespace App\Http\Controllers\Ledger;

use App\Http\Controllers\Controller;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class DuplicateController extends Controller
{
    /**
     * 既存の台帳レコードを元に新規作成画面を表示
     *
     * @return \Illuminate\Contracts\View\View
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function duplicate(Request $request)
    {
        // 複製元のレコードを取得
        $sourceLedgerId = (int) $request->route('ledgerId');
        $sourceLedger = Ledger::with(['define'])->findOrFail($sourceLedgerId);

        // 複製元の閲覧権限チェック
        $this->authorize('view', $sourceLedger);

        $ledgerDefine = $sourceLedger->define;

        if (! $ledgerDefine) {
            abort(404, __('ledger.define_not_found'));
        }

        // 新規作成権限チェック
        if (auth()->user()->cannot('create', [Ledger::class, $ledgerDefine])) {
            abort(403, __('ledger.not_allow_create'));
        }

        // 複製元のcontentからprefillパラメータを構成
        $prefillParams = $this->buildPrefillParamsFromLedger($sourceLedger, $ledgerDefine);

        // ── パンくずリストの取得 ──────────────────────────────────────
        $breadcrumbs = [];
        if ($ledgerDefine->folder_id) {
            $folder = \App\Models\Folder::with('ancestors')->find($ledgerDefine->folder_id);
            if ($folder) {
                $breadcrumbs = $folder->ancestors->all();
                $breadcrumbs[] = $folder;
            }
        }

        // 既存のCreateController::create()と同じビューを返す
        return View::make('ledger.create', [
            'ledgerDefineRecord' => $ledgerDefine,
            'prefillParams' => $prefillParams,
            'breadcrumbs' => $breadcrumbs,
            'sourceLedgerId' => $sourceLedgerId, // 監査ログ用（オプション）
        ]);
    }

    /**
     * 複製元レコードからprefillパラメータを構成
     */
    private function buildPrefillParamsFromLedger(Ledger $sourceLedger, LedgerDefine $ledgerDefine): array
    {
        $prefillParams = [];
        $columnDefines = collect($ledgerDefine->column_define);

        // 除外するカラムタイプ
        // - YMD, YMDHM: 毎回新しい日付を入力する（日報の作成日等）
        // - auto_number: システムが自動採番する
        // - files: 毎回異なる資料を添付する（日報の写真等）
        $excludedTypes = ['YMD', 'YMDHM', 'auto_number', 'files'];

        foreach ($sourceLedger->content as $columnId => $value) {
            $column = $columnDefines->firstWhere('id', (int) $columnId);

            // カラム定義が存在しない、または除外タイプの場合はスキップ
            if (! $column || in_array($column->type, $excludedTypes)) {
                continue;
            }

            if ($column->type === 'chk') {
                $value = $this->normalizeCheckboxPrefillValue($value, $column->options ?? []);

                if ($value === []) {
                    continue;
                }

                $prefillParams[$columnId] = $value;

                continue;
            }

            // 値のサニタイズ（文字列の場合）
            if (is_string($value)) {
                $value = strip_tags($value);
                $value = mb_substr($value, 0, 5000); // 最大5000文字
            }

            // 配列の場合（chk など）
            if (is_array($value)) {
                $value = array_map(function ($item) {
                    return is_string($item) ? strip_tags(mb_substr($item, 0, 255)) : $item;
                }, $value);

                // select/chk の場合、現在の選択肢に存在するもののみ
                if (in_array($column->type, ['select', 'chk']) && ! empty($column->options)) {
                    $value = array_values(array_filter($value, fn ($v) => in_array($v, $column->options, true)));

                    // フィルタリング後に空配列になった場合はスキップ
                    if (empty($value)) {
                        continue;
                    }
                }
            }

            // select の単一値の場合も選択肢チェック
            if ($column->type === 'select' && ! empty($column->options)) {
                if (! in_array($value, $column->options, true)) {
                    continue; // 現在の選択肢に存在しない場合はスキップ
                }
            }

            $prefillParams[$columnId] = $value;
        }

        return $prefillParams;
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
