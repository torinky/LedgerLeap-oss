<?php

namespace App\Services\Ledger;

use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDiff;
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
     */
    public function processContentForDisplay(
        Ledger $ledgerRecord,
        ?LedgerDiff $comparisonTargetDiff,
        int $displayLevel,
        Collection $allAttachments,
        ?string $highlight = null,
        ?int $baseDiffId = null,
        bool $showChanges = false
    ): array {
        // 1. 差分データを取得
        $diffResult = $this->ledgerDiffProcessor->prepareContentDiff($ledgerRecord, $comparisonTargetDiff, $baseDiffId);
        $contentChanges = $diffResult['contentChanges'];

        // 2. カラムIDと配列インデックスの対応マップを作成 (AsColumnArrayJson用)
        $columnIdToIndex = collect(optional($ledgerRecord->define)->column_define ?? [])
            ->sortBy('id')
            ->pluck('id')
            ->values()
            ->flip();

        // 3. 全てのカラムを取得 (フィルタリングは後のループで行い、省略を検知する)
        $allColumns = collect(optional($ledgerRecord->define)->column_define ?? []);

        // Mock Attachment Column Injection
        if (MockAttachmentService::isEnabled()) {
            $allColumns->push(MockAttachmentService::getMockColumnDefine());
        }

        // 4. カラムのグルーピングとソート
        $groupedColumns = $allColumns->groupBy(function ($column) {
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
            $columnObjectsInGroup = $columnsInGroup->map(fn ($c) => new ColumnDefine($c));

            $displayGroup = [
                'group_name' => $groupName,
                'is_required_group' => $columnObjectsInGroup->contains(fn (ColumnDefine $col) => $col->required),
                'columns' => [],
            ];

            $omittedCount = 0;
            foreach ($columnObjectsInGroup as $columnDefine) {
                $columnDisplayLevel = $columnDefine->display_level ?? 3;
                if ($columnDisplayLevel > $displayLevel) {
                    $omittedCount++;

                    continue;
                }

                // 省略されたカラムがあった場合、履歴として挿入
                if ($omittedCount > 0) {
                    $displayGroup['columns'][] = [
                        'is_omitted' => true,
                        'omitted_count' => $omittedCount,
                    ];
                    $omittedCount = 0;
                }

                $change = $contentChanges[$columnDefine->id] ?? null;

                // changeがない場合の処理
                if (! $change) {
                    // 比較モードまたは履歴表示モードの場合
                    // カラム定義が存在しない（そのバージョンでは未作成）場合は表示をスキップまたは「なし」とする
                    if ($showChanges || $baseDiffId !== null) {
                        if ($showChanges) {
                            continue;
                        }
                        // 履歴スナップショット表示で、その時点では存在しなかったカラムの場合
                        $currentValueHtml = '<div class="flex w-full justify-center items-center text-base-content/20 italic text-xs">'.__('ledger.diff.not_defined_at_this_version').'</div>';
                        $oldValueHtml = '';
                    } else {
                        // 通常の最新表示モードで、何らかの理由でデータがない場合
                        $contentIndex = $columnIdToIndex->get($columnDefine->id);
                        $currentValue = ($contentIndex !== null && isset($ledgerRecord->content[$contentIndex]))
                            ? $ledgerRecord->content[$contentIndex]
                            : null;

                        $change = [
                            'status' => 'unchanged',
                            'current_value' => $currentValue,
                            'old_value' => $currentValue,
                        ];
                    }
                }

                if ($change) { // changeが存在する場合の通常のHTML生成
                    // 5. HTMLの生成
                    $currentValueHtml = '';
                    if ($change['status'] === 'deleted') {
                        $currentValueHtml = '<div class="flex w-full justify-center items-center text-success-content/50"><x-mary-icon name="o-trash" label="'.__('ledger.diff.deleted').'" class="w-5 h-5" /></div>';
                    } else {
                        $contentIndex = $columnIdToIndex->get($columnDefine->id);
                        $attachedMetaData = ($contentIndex !== null && isset($ledgerRecord->content_attached[$contentIndex]))
                            ? $ledgerRecord->content_attached[$contentIndex]
                            : [];

                        $currentValueHtml = (string) $this->columnHtmlService
                            ->setAttachmentCollection($allAttachments)
                            ->setAttachmentContents($attachedMetaData)
                            ->setSource('ledger-content-processor')
                            ->show($columnDefine, $change['current_value'], true, [], '', false, $ledgerRecord, $highlight);
                    }

                    $oldValueHtml = '';
                    if ($change['status'] === 'added') {
                        $oldValueHtml = '<div class="flex w-full justify-center items-center text-success-content/50"><x-mary-icon name="o-cube" label="'.__('ledger.diff.not_exist').'" class="w-5 h-5" /></div>';
                    } else {
                        // 過去バージョンの表示では、過去のcontent_attachedは存在しないため、
                        // allAttachments（全バージョンの添付ファイル）のみを渡して解決させる
                        $oldValueHtml = (string) $this->columnHtmlService
                            ->setAttachmentCollection($allAttachments)
                            ->setAttachmentContents([]) // 過去の個別指定はクリア
                            ->setSource('ledger-content-processor')
                            ->show($columnDefine, $change['old_value'], true, [], '', false, $ledgerRecord, $highlight);
                    }

                    $displayGroup['columns'][] = [
                        'id' => $columnDefine->id,
                        'type' => $columnDefine->type,
                        'name' => $columnDefine->name,
                        'hint' => $columnDefine->hint,
                        'is_required' => $columnDefine->required,
                        'status' => $change['status'],
                        'current_value_html' => $currentValueHtml,
                        'old_value_html' => $oldValueHtml,
                        'is_omitted' => false,
                    ];
                }
            }

            // 最後に省略されたカラムがあった場合
            if ($omittedCount > 0) {
                $displayGroup['columns'][] = [
                    'is_omitted' => true,
                    'omitted_count' => $omittedCount,
                ];
            }

            if (! empty($displayGroup['columns'])) {
                $displayData[] = $displayGroup;
            }
        }

        return [
            'displayData' => $displayData,
            'hasChangedColumns' => $diffResult['hasChangedColumns'],
        ];
    }
}
