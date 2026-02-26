<?php

namespace App\Services\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;

class LedgerDiffProcessor
{
    /**
     * 比較対象となる過去のLedgerDiffを特定するロジック
     */
    public function findComparisonTargetDiff(Ledger $ledgerRecord, ?int $referenceDiffId = null): ?LedgerDiff
    {
        $baseId = $referenceDiffId ?? $ledgerRecord->latest_diff_id;
        $currentRawContent = $ledgerRecord->getRawOriginal('content');

        // もし referenceDiffId が指定されている場合は、そのレコードの内容を基準にする
        if ($referenceDiffId) {
            $refDiff = LedgerDiff::find($referenceDiffId);
            if ($refDiff) {
                $currentRawContent = $refDiff->getRawOriginal('content');
                // バージョンも基準に合わせる
                $referenceVersion = $refDiff->version;
            }
        }
        $referenceVersion = $referenceVersion ?? $ledgerRecord->version;

        if (! $baseId || $currentRawContent === null || $currentRawContent === '' || $currentRawContent === '[]') {
            return null;
        }

        // まずは内容が異なる直近のDiffを探す
        $target = LedgerDiff::where('ledger_id', $ledgerRecord->id)
            ->whereNotNull('content')
            ->where('content', '<>', '[]')
            ->where('id', '<', $baseId)
            ->whereRaw('content != ?', [$currentRawContent])
            ->orderBy('id', 'desc')
            ->first();

        // 内容が異なるものが見つからない、または ID で見つからない場合
        // バージョン番号での比較も試みる（テスト環境対策）
        if (! $target && $referenceVersion !== null) {
            $target = LedgerDiff::where('ledger_id', $ledgerRecord->id)
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->where('version', '<', $referenceVersion)
                ->orderBy('version', 'desc')
                ->first();
        }

        // それでも見つからない場合は、内容が同じでもIDが手前のものを探す（ステータス変更のみの場合など）
        if (! $target) {
            $target = LedgerDiff::where('ledger_id', $ledgerRecord->id)
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->where('id', '<', $baseId)
                ->orderBy('id', 'desc')
                ->first();
        }

        return $target;
    }

    /**
     * 空値を正規化する
     * null, 空文字列, 空配列を全て null に統一することで、実質的に「空」である値を同一視する
     *
     * @param  mixed  $value  正規化対象の値
     * @return mixed 正規化後の値 (空の場合は null)
     */
    private function normalizeEmptyValue(mixed $value): mixed
    {
        // null, 空文字列, 空配列は全て null に統一
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        // 配列の場合、中身が全て空（null または ''）なら null に統一
        if (is_array($value)) {
            $filtered = array_filter($value, fn ($v) => $v !== null && $v !== '');
            if (empty($filtered)) {
                return null;
            }
        }

        return $value;
    }

    /**
     * 差分表示のためのデータを準備する
     */
    public function prepareContentDiff(Ledger $ledgerRecord, ?LedgerDiff $comparisonTargetDiff, ?int $baseDiffId = null): array
    {
        $baseDiff = null;
        if ($baseDiffId) {
            $baseDiff = LedgerDiff::find($baseDiffId);
        }

        $currentContent = $baseDiff ? ($baseDiff->content ?? []) : ($ledgerRecord->content ?? []);
        $currentColumnDefines = collect($baseDiff ? ($baseDiff->column_define ?? []) : (optional($ledgerRecord->define)->column_define ?? []))->keyBy('id');
        //        \Illuminate\Support\Facades\Log::debug('LedgerDiffProcessor prepareContentDiff - currentColumnDefines:', $currentColumnDefines->toArray());

        if (! $comparisonTargetDiff) {
            // 比較対象がない場合は、現在の内容のみを整形して返す
            $normalizedContent = optional($ledgerRecord->define)->normalizeByColumnDefine($currentContent) ?? [];
            $sortedIds = collect(optional($ledgerRecord->define)->column_define ?? [])->sortBy('id')->pluck('id')->values();

            $contentChanges = $currentColumnDefines->map(function ($colDef) use ($normalizedContent, $sortedIds) {
                $columnId = data_get($colDef, 'id');
                $contentIndex = $sortedIds->search($columnId);
                $value = ($contentIndex !== false && isset($normalizedContent[$contentIndex])) ? $normalizedContent[$contentIndex] : null;

                // 比較対象がない場合は、最初のバージョンの全カラムが追加されたものとみなす
                $status = 'added';

                return [
                    'column_define' => is_array($colDef) ? $colDef : $colDef->toArray(),
                    'current_value' => $value,
                    'old_value' => $value, // 比較がない場合は同じ値
                    'status' => $status,
                ];
            })->all();

            return [
                'contentChanges' => $contentChanges,
                'hasChangedColumns' => false,
            ];
        }

        // 比較対象がある場合
        $oldContent = $comparisonTargetDiff->content ?? [];
        $oldColumnDefines = collect($comparisonTargetDiff->column_define ?? [])->keyBy('id');

        $allColumnIds = $currentColumnDefines->keys()->merge($oldColumnDefines->keys())->unique()->values();

        $currentSortedIds = $currentColumnDefines->sortBy('id')->pluck('id')->values();
        $oldSortedIds = $oldColumnDefines->sortBy('id')->pluck('id')->values();

        $contentChanges = [];
        $hasChangedColumns = false;

        foreach ($allColumnIds as $columnId) {
            $currentColDef = $currentColumnDefines->get($columnId);
            $oldColDef = $oldColumnDefines->get($columnId);

            $currentValue = null;
            if ($currentColDef) {
                $currentIndex = $currentSortedIds->search($columnId);
                if ($currentIndex !== false && isset($currentContent[$currentIndex])) {
                    $currentValue = $currentContent[$currentIndex];
                }
            }

            $oldValue = null;
            if ($oldColDef) {
                $oldIndex = $oldSortedIds->search($columnId);
                if ($oldIndex !== false && isset($oldContent[$oldIndex])) {
                    $oldValue = $oldContent[$oldIndex];
                }
            }

            // 正規化後の値で比較を実施
            $normalizedCurrent = $this->normalizeEmptyValue($currentValue);
            $normalizedOld = $this->normalizeEmptyValue($oldValue);

            $status = 'unchanged';

            if (! $oldColDef) {
                // 古い定義に存在しない（追加された）カラムの場合
                // 現在の値が空であれば「変更なし」とみなす（スキーマ追加による差分を無視）
                $status = ($normalizedCurrent === null) ? 'unchanged' : 'added';

                // 追加されたカラムで値が空の場合は empty として明示
                if ($status === 'unchanged' && $normalizedCurrent === null) {
                    $status = 'empty';
                }
            } elseif (! $currentColDef) {
                $status = 'deleted';
            } elseif (json_encode($normalizedCurrent) !== json_encode($normalizedOld)) {
                $status = 'modified';
            }

            if ($status !== 'unchanged' && $status !== 'empty') {
                $hasChangedColumns = true;
            }

            $colDefForInfo = $currentColDef ?? $oldColDef;
            $colDefArray = $colDefForInfo ? (is_array($colDefForInfo) ? $colDefForInfo : $colDefForInfo->toArray()) : [];

            $contentChanges[$columnId] = [
                'column_define' => $colDefArray,
                'current_value' => $currentValue,
                'old_value' => $oldValue,
                'status' => $status,
            ];
        }

        $sortedContentChanges = collect($contentChanges)->sortBy(function ($change) {
            return data_get($change['column_define'], 'order', PHP_INT_MAX);
        })->all();

        return [
            'contentChanges' => $sortedContentChanges,
            'hasChangedColumns' => $hasChangedColumns,
        ];
    }
}
