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
     * @param string|null $highlight
     * @return array 表示用のカラムデータ配列
     */
    public function processContentForDisplay(Ledger $ledgerRecord, LedgerDefine $ledgerDefine, ?string $highlight = null): array
    {
        $displayColumns = [];
        $content = $ledgerRecord->content; // JSON形式の文字列を想定

        // column_defines は AsColumnDefinesArrayJson キャストによって ColumnDefine オブジェクトの配列になっているはず
        $columnDefines = $ledgerDefine->column_define ?? [];
        foreach ($columnDefines as $columnDefine) {
            /** @var ColumnDefine $columnDefine */
            // Ledgerのcontentはcolumn_defineのIDをキーとして値を保持する
            $value = $content[$columnDefine->id] ?? null;

            // ColumnHtmlService::show() の引数に合わせて修正
            // show(object|array $columnDefineData, $initialValue, bool $canView = true, array $attrs = [], string $idPrefix = '', bool $asCreate = false, ?Ledger $record = null, ?string $highlight = null): HtmlString
            $html = $this->columnHtmlService->show(
                $columnDefine, // $columnDefineData
                $value,        // $initialValue
                true,          // $canView (デフォルト値)
                [],            // $attrs (空の配列を渡す)
                '',            // $idPrefix (デフォルト値)
                false,         // $asCreate (デフォルト値)
                $ledgerRecord, // $record
                $highlight     // $highlight
            );

            $displayColumns[] = [
                'name' => $columnDefine->name,
                'label' => $columnDefine->getInputType()->getLabel(),
                'type' => $columnDefine->type,
                'html' => (string) $html, // HtmlString を文字列にキャスト
            ];
        }

        return $displayColumns;
    }
}
