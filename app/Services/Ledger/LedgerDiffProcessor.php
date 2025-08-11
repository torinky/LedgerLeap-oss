<?php

namespace App\Services\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDiff;
use App\Models\LedgerDefine;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use Arr;
use Illuminate\Support\Facades\Log;

class LedgerDiffProcessor
{
    /**
     * 比較対象となる過去のLedgerDiffを特定するロジック
     * 例: このワークフローの「実質的な開始点」のDiff (最後にDRAFTでなく、内容が記録されたもの)
     *
     * @param Ledger $ledgerRecord
     * @return ?LedgerDiff
     */
    public function findComparisonTargetDiff(Ledger $ledgerRecord): ?LedgerDiff
    {
        $latestDiffId = $ledgerRecord->latest_diff_id;
        // contentの「キャスト前」値を取得
        $currentRawContent = $ledgerRecord->getRawOriginal('content');

        if (!$latestDiffId || $currentRawContent === null || $currentRawContent === '' || $currentRawContent === '[]') {
            // 最新Diffがない場合や現在のcontentが空の場合
            return null;
        }

        // SQLでcontentが現在のcontentと異なる直近のDiffを取得（キャスト前の値で比較）
        $diff = LedgerDiff::where('ledger_id', $ledgerRecord->id)
            ->whereNotNull('content')
            ->where('content', '<>', '[]')
            ->where('id', '<', $latestDiffId)
            ->whereRaw('content != ?', [$currentRawContent])
            ->orderBy('id', 'desc')
            ->first();

        return $diff;
    }

    public function prepareContentDiff(Ledger $ledgerRecord, LedgerDefine $ledgerDefineRecord, ?LedgerDiff $comparisonTargetDiff): array
    {
        $contentChanges = [];
        $currentContentArray = $ledgerRecord->content ?? [];
        $currentContentAttached = $ledgerRecord->content_attached ?? [];

        // 現在のレコードの添付ファイル情報を取得
        $currentAttachments = AttachedFile::where('ledger_id', $ledgerRecord->id)
            ->get()
            ->keyBy('hashedbasename');

        // 比較対象の古いレコードの添付ファイル情報を取得
        $oldAttachments = collect();
        if ($comparisonTargetDiff) {
            // 古いDiffのcontentに含まれるファイルのみを対象にする
            $oldFileHashes = array_keys($comparisonTargetDiff->content ?? []);
            if (!empty($oldFileHashes)) {
                $oldAttachments = AttachedFile::where('ledger_id', $comparisonTargetDiff->ledger_id)
                    ->whereIn('hashedbasename', $oldFileHashes)
                    ->get()
                    ->keyBy('hashedbasename');
            }
        }

        $currentColumnDefines = ColumnDefine::normalizeArrayOrCollection($ledgerDefineRecord->column_define);

        $hasComparison = $comparisonTargetDiff && isset($comparisonTargetDiff->column_define);

        $oldColumnDefines = $hasComparison
            ? ColumnDefine::normalizeArrayOrCollection($comparisonTargetDiff->column_define)
            : [];

        $oldContentArray = $hasComparison
            ? ($comparisonTargetDiff->content ?? [])
            : [];
        $oldContentAttached = $hasComparison ? ($comparisonTargetDiff->content_attached ?? []) : [];

        // ★ 現在と過去のすべてのカラムIDを取得し、ユニークにする
        $allColumnIds = array_unique(array_merge(
            array_keys($currentColumnDefines),
            array_keys($oldColumnDefines)
        ));

        // ★ ソート順を決定するための情報を集める
        $columnOrders = [];
        foreach ($allColumnIds as $id) {
            // 現在の定義にあればそのorderを、なければ過去のorderを、どちらもなければ非常に大きい値を使う
            $order = PHP_INT_MAX;
            if (isset($currentColumnDefines[$id])) {
                $order = data_get($currentColumnDefines[$id], 'order', PHP_INT_MAX);
            } elseif (isset($oldColumnDefines[$id])) {
                $order = data_get($oldColumnDefines[$id], 'order', PHP_INT_MAX);
            }
            $columnOrders[$id] = $order;
        }

        // ★ orderでソート
        asort($columnOrders);
        $sortedColumnIds = array_keys($columnOrders);

        $hasChangedColumns = false;

        foreach ($sortedColumnIds as $columnId) {
            $currentColDef = $currentColumnDefines[$columnId] ?? null;
            $oldColDef = $oldColumnDefines[$columnId] ?? null;

            // Get the 0-based index of the column in the content array
            $contentIndex = array_search($columnId, $sortedColumnIds);

            $currentRawValue = Arr::get($currentContentArray, $contentIndex);
            $currentValue = (is_object($currentRawValue) || is_array($currentRawValue))
                ? json_decode(json_encode($currentRawValue), true)
                : $currentRawValue;

            $oldRawValue = $hasComparison ? Arr::get($oldContentArray, $contentIndex) : null;
            $oldValue = (is_object($oldRawValue) || is_array($oldRawValue))
                ? json_decode(json_encode($oldRawValue), true)
                : $oldRawValue;

            $normalizedCurrent = (is_array($currentValue) || is_object($currentValue)) ? json_encode($currentValue) : (string)$currentValue;
            $normalizedOld = (is_array($oldValue) || is_object($oldValue)) ? json_encode($oldValue) : (string)$oldValue;
            $isChanged = $hasComparison && ($normalizedCurrent !== $normalizedOld);

            if ($isChanged) {
                $hasChangedColumns = true;
            }

            if (isset($currentColDef['name'])) {
                $columnName = $currentColDef['name'];
            } elseif (isset($oldColDef['name'])) {
                $columnName = $oldColDef['name'];
            } else {
                $columnName = __('ledger.column_deleted', ['id' => $columnId]);
            }

            $contentChanges[$columnId] = [
                'column_define_current' => $currentColDef,
                'current_value' => $currentValue,
                'column_define_old' => $oldColDef,
                'old_value' => $oldValue,
                'changed' => $isChanged,
                'column_name' => $columnName,
                'current_attachments' => $currentAttachments,
                'old_attachments' => $oldAttachments,
                'current_attachment_contents' => $currentContentAttached[$columnId] ?? [],
                'old_attachment_contents' => $oldContentAttached[$columnId] ?? [],
            ];
        }

        return [
            'contentChanges' => $contentChanges,
            'hasChangedColumns' => $hasChangedColumns,
        ];
    }
}
