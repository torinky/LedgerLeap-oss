<?php

namespace App\Services;

use App\Models\Ledger;
use Illuminate\Support\Str;

class NumberingService
{
    /**
     * 次の自動採番番号を生成する
     *
     * @param object $columnDefine カラム定義オブジェクト (id, name, type, options, uniqueなど)
     * @param int $ledgerDefineId 台帳定義ID
     * @return string 次の採番番号
     */
    public function getNextNumber(object $columnDefine, int $ledgerDefineId): string
    {
        $prefix = $columnDefine->options->prefix ?? '';
        $digits = $columnDefine->options->digits ?? 3;
        $revision = $columnDefine->options->revision ?? '';
        $isUnique = $columnDefine->unique ?? false;
        $columnId = $columnDefine->id;

        $maxNumber = 0;

        // 対象の台帳定義に紐づく全てのレコードのcontentを取得
        // contentはJSON形式で保存されているため、PHP側でパースして処理する
        $ledgers = Ledger::where('ledger_define_id', $ledgerDefineId)->get();

        foreach ($ledgers as $ledger) {
            $contentValue = $ledger->content[$columnId] ?? null;

            if (is_string($contentValue)) {
                $pattern = '';
                if ($isUnique) {
                    // uniqueがtrueの場合、版記号は無視して接頭辞と数値部分のみを考慮
                    $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)(.*)$/';
                } else {
                    // uniqueがfalseの場合、接頭辞と版記号が一致するものを考慮
                    $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($revision, '/') . '$/';
                }

                if (preg_match($pattern, $contentValue, $matches)) {
                    $currentNumber = (int) $matches[1];
                    if ($currentNumber > $maxNumber) {
                        $maxNumber = $currentNumber;
                    }
                }
            }
        }

        $nextNumber = $maxNumber + 1;

        // ゼロ埋め
        $formattedNumber = str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);

        // 最終的な番号文字列を組み立て
        return $prefix . $formattedNumber . $revision;
    }
}
