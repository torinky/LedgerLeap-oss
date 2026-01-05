<?php

namespace App\Services\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;

class LedgerDiffProcessor
{
    /**
     * 比較対象となる過去のLedgerDiffを特定するロジック
     */
    public function findComparisonTargetDiff(Ledger $ledgerRecord): ?LedgerDiff
    {
        $latestDiffId = $ledgerRecord->latest_diff_id;
        $currentRawContent = $ledgerRecord->getRawOriginal('content');

        if (! $latestDiffId || $currentRawContent === null || $currentRawContent === '' || $currentRawContent === '[]') {
            return null;
        }

        // まずは内容が異なる直近のDiffを探す
        $target = LedgerDiff::where('ledger_id', $ledgerRecord->id)
            ->whereNotNull('content')
            ->where('content', '<>', '[]')
            ->where('id', '<', $latestDiffId)
            ->whereRaw('content != ?', [$currentRawContent])
            ->orderBy('id', 'desc')
            ->first();

        // 内容が異なるものが見つからない、または ID で見つからない場合
        // バージョン番号での比較も試みる（テスト環境対策）
        if (! $target) {
            $target = LedgerDiff::where('ledger_id', $ledgerRecord->id)
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->where('version', '<', $ledgerRecord->version)
                ->orderBy('version', 'desc')
                ->first();
        }

        // それでも見つからない場合は、内容が同じでもIDが手前のものを探す（ステータス変更のみの場合など）
        if (! $target) {
            $target = LedgerDiff::where('ledger_id', $ledgerRecord->id)
                ->whereNotNull('content')
                ->where('content', '<>', '[]')
                ->where('id', '<', $latestDiffId)
                ->orderBy('id', 'desc')
                ->first();
        }

        return $target;
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
            $contentChanges = $currentColumnDefines->map(function ($colDef) use ($currentContent, $ledgerRecord) {
                $normalizedContent = optional($ledgerRecord->define)->normalizeByColumnDefine($currentContent) ?? [];
                $sortedIds = collect(optional($ledgerRecord->define)->column_define ?? [])->sortBy('id')->pluck('id')->values();
                $contentIndex = $sortedIds->search($colDef->id);
                $value = ($contentIndex !== false && isset($normalizedContent[$contentIndex])) ? $normalizedContent[$contentIndex] : null;

                $status = 'added';
                if (empty($value)) {
                    $status = 'empty';
                }

                return [
                    'column_define' => $colDef->toArray(),
                    'current_value' => $value,
                    'old_value' => null,
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
        \Illuminate\Support\Facades\Log::debug('LedgerDiffProcessor prepareContentDiff - oldColumnDefines:', $oldColumnDefines->toArray());

        $allColumnIds = $currentColumnDefines->keys()->merge($oldColumnDefines->keys())->unique()->values();
        \Illuminate\Support\Facades\Log::debug('LedgerDiffProcessor prepareContentDiff - allColumnIds:', $allColumnIds->toArray());

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

            $status = 'unchanged';
            if (empty($currentValue)) {
                $status = 'empty';
            }
            if (! $oldColDef) {
                $status = 'added';
            } elseif (! $currentColDef) {
                $status = 'deleted';
            } elseif (json_encode($currentValue) !== json_encode($oldValue)) {
                $status = 'modified';
            }

            if ($status !== 'unchanged' && $status !== 'empty') {
                $hasChangedColumns = true;
            }

            $colDefForInfo = $currentColDef ?? $oldColDef;
            $colDefArray = $colDefForInfo ? $colDefForInfo->toArray() : [];

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
