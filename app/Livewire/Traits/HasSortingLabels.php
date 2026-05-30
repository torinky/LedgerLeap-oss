<?php

namespace App\Livewire\Traits;

use App\Models\LedgerDefine;

trait HasSortingLabels
{
    /**
     * 標準的なソート列のラベルを取得します。
     */
    protected function getStandardSortLabel(string $columnName): string
    {
        return match ($columnName) {
            'composite_score' => __('ledger.scoring.score'),
            'created_at' => __('ledger.created_at'),
            'updated_at' => __('ledger.updated_at'),
            'semantic_score' => __('ledger.semantic_score_sort'),
            'default' => $this->getDefaultSortLabel(),
            default => $this->getColumnLabel($columnName),
        };
    }

    /**
     * 動的なカラム名のラベルを取得する
     */
    protected function getColumnLabel(string $columnName): string
    {
        // content->ID 形式のカラム名からラベルを解決
        if (str_starts_with($columnName, 'content->')) {
            // 単一台帳選択時のみ具体的な項目名を表示
            if (count($this->selectedLedgerDefineIds) === 1) {
                $columnId = str_replace('content->', '', $columnName);
                $singleLedgerDefine = LedgerDefine::find(head($this->selectedLedgerDefineIds));

                if ($singleLedgerDefine) {
                    $column = collect($singleLedgerDefine->column_define)
                        ->first(fn ($col) => (string) $col->id === (string) $columnId);

                    if ($column) {
                        return $column->name;
                    }
                }
            }

            // 複数台帳選択時や項目が見つからない場合は汎用的なラベルを表示
            return __('ledger.column.custom_column_sort');
        }

        return '';
    }

    /**
     * デフォルトのソートラベルを取得する
     */
    protected function getDefaultSortLabel(): string
    {
        $label = __('ledger.default_sort_order');

        // 単一台帳選択時のみ具体的な項目名を表示
        if (count($this->selectedLedgerDefineIds) === 1 && ! empty($this->defaultSortColumns)) {
            $columnNames = collect($this->defaultSortColumns)->pluck('name')->implode(', ');
            $label .= " ({$columnNames})";
        }

        return $label;
    }
}
