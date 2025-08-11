<?php

namespace App\Services\Ledger;

use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\ColumnDefine;
use App\Services\Ledger\ColumnHtmlService;

class LedgerContentProcessor
{
    protected ColumnHtmlService $columnHtmlService;

    public function __construct(ColumnHtmlService $columnHtmlService)
    {
        $this->columnHtmlService = $columnHtmlService;
    }

    /**
     * 台帳レコードのコンテンツとカラム定義を元に、表示用のカラムデータを生成する。
     *
     * @param Ledger $ledgerRecord
     * @param LedgerDefine $ledgerDefine
     * @return array 表示用のカラムデータ配列
     */
    public function processContentForDisplay(Ledger $ledgerRecord, LedgerDefine $ledgerDefine): array
    {
        $displayColumns = [];
        $content = $ledgerRecord->content; // JSON形式の文字列を想定

        // column_defines は AsColumnDefinesArrayJson キャストによって ColumnDefine オブジェクトの配列になっているはず
        $columnDefines = $ledgerDefine->column_defines ?? [];
        foreach ($columnDefines as $columnDefine) {
            /** @var ColumnDefine $columnDefine */
            $value = null;

            // content から対応する値を取得
            if (isset($content[$columnDefine->name])) {
                $value = $content[$columnDefine->name];
            }

            // ColumnHtmlService を使ってHTMLを生成
            $html = $this->columnHtmlService->render($columnDefine, $value);

            $displayColumns[] = [
                'name' => $columnDefine->name,
                'label' => $columnDefine->label,
                'type' => $columnDefine->type,
                'html' => $html,
            ];
        }

        return $displayColumns;
    }
}
