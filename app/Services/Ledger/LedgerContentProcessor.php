<?php

namespace App\Services\Ledger;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\LedgerDiff;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

class LedgerContentProcessor
{
    protected ColumnHtmlService $columnHtmlService;
    protected LedgerDiffProcessor $ledgerDiffProcessor;

    public function __construct(
        ColumnHtmlService $columnHtmlService,
        LedgerDiffProcessor $ledgerDiffProcessor
    ) {
        $this->columnHtmlService = $columnHtmlService;
        $this->ledgerDiffProcessor = $ledgerDiffProcessor;
    }

    /**
     * 台帳のレコード、差分、表示レベル、添付ファイル情報を元に、
     * 表示に必要なすべての情報（グループ、カラム、HTML）を含む完成されたデータ構造を生成する。
     *
     * @param Ledger $ledgerRecord
     * @param LedgerDiff|null $comparisonTargetDiff
     * @param int $displayLevel
     * @param EloquentCollection $allAttachments
     * @param string|null $highlight
     * @return array
     */
    public function processContentForDisplay(
        Ledger $ledgerRecord,
        ?LedgerDiff $comparisonTargetDiff,
        int $displayLevel,
        EloquentCollection $allAttachments,
        ?string $highlight = null
    ): array {
        // 1. 差分データを取得
        $diffResult = $this->ledgerDiffProcessor->prepareContentDiff($ledgerRecord, $comparisonTargetDiff);
        $contentChanges = $diffResult['contentChanges'];

        // 2. カラムのフィルタリング
        $filteredColumns = collect($ledgerRecord->define->column_define)
            ->filter(function ($column) use ($displayLevel) {
                $columnDisplayLevel = is_array($column) ? ($column['display_level'] ?? 3) : ($column->display_level ?? 3);
                return $columnDisplayLevel <= $displayLevel;
            });

        // 3. カラムのグルーピングとソート
        $groupedColumns = $filteredColumns->groupBy(function ($column) {
            $group = is_array($column) ? ($column['group'] ?? '') : ($column->group ?? '');
            return $group === '' ? __('ledger.form.group_default') : $group;
        })->sortBy(function ($columns, $groupName) {
            if ($columns->isNotEmpty()) {
                $firstColumn = $columns->first();
                return is_array($firstColumn) ? ($firstColumn['order'] ?? PHP_INT_MAX) : ($firstColumn->order ?? PHP_INT_MAX);
            }
            return $groupName;
        });

        // 4. 最終データ構造の組み立て
        $displayData = [];
        foreach ($groupedColumns as $groupName => $columnsInGroup) {
            $columnObjectsInGroup = $columnsInGroup->map(fn($c) => new ColumnDefine($c));

            $displayGroup = [
                'group_name' => $groupName,
                'is_required_group' => $columnObjectsInGroup->contains(fn(ColumnDefine $col) => $col->required),
                'columns' => [],
            ];

            foreach ($columnObjectsInGroup as $columnDefine) {
                $change = $contentChanges[$columnDefine->id] ?? null;
                if (!$change) {
                    continue;
                }

                // 5. HTMLの生成
                $currentValueHtml = '';
                if ($change['status'] === 'deleted') {
                    $currentValueHtml = '<div class="flex w-full justify-center items-center text-success-content/50"><x-mary-icon name="o-trash" label="' . __('ledger.diff.deleted') . '" class="w-5 h-5" /></div>';
                } else {
                    $currentValueHtml = (string) $this->columnHtmlService
                        ->setAttachmentCollection($allAttachments)
                        ->setAttachmentContents($ledgerRecord->content_attached[$columnDefine->id] ?? [])
                        ->show($columnDefine, $change['current_value'], true, [], '', false, $ledgerRecord, $highlight);
                }

                $oldValueHtml = '';
                if ($change['status'] === 'added') {
                    $oldValueHtml = '<div class="flex w-full justify-center items-center text-success-content/50"><x-mary-icon name="o-cube" label="' . __('ledger.diff.not_exist') . '" class="w-5 h-5" /></div>';
                } elseif ($change['status'] !== 'unchanged' && $change['status'] !== 'deleted') {
                    // 過去バージョンの表示では、過去のcontent_attachedは存在しないため、
                    // allAttachments（全バージョンの添付ファイル）のみを渡して解決させる
                    $oldValueHtml = (string) $this->columnHtmlService
                        ->setAttachmentCollection($allAttachments)
                        ->setAttachmentContents([]) // 過去の個別指定はクリア
                        ->show($columnDefine, $change['old_value'], true, [], '', false, $ledgerRecord, $highlight);
                }

                $displayGroup['columns'][] = [
                    'id' => $columnDefine->id,
                    'name' => $columnDefine->name,
                    'hint' => $columnDefine->hint,
                    'is_required' => $columnDefine->required,
                    'status' => $change['status'],
                    'current_value_html' => $currentValueHtml,
                    'old_value_html' => $oldValueHtml,
                ];
            }

            if (!empty($displayGroup['columns'])) {
                $displayData[] = $displayGroup;
            }
        }

        return [
            'displayData' => $displayData,
            'hasChangedColumns' => $diffResult['hasChangedColumns'],
        ];
    }
}
