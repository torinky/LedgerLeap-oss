<?php

namespace App\Services;

use App\Models\ColumnTypes\AutoNumberType;
use App\Models\Ledger;
use Illuminate\Support\Str;

class NumberingService
{
    /**
     * 次の自動採番番号を生成する
     *
     * @param AutoNumberType $columnType カラム定義オブジェクト (id, name, type, options, uniqueなど)
     * @param int $ledgerDefineId 台帳定義ID
     * @return string 次の採番番号
     */
    public function getNextNumber(\App\Models\ColumnDefine $columnDefine, int $ledgerDefineId): string
    {
        $columnType = $columnDefine->getInputType(); // AutoNumberType オブジェクトを取得
        $columnId = $columnDefine->id; // ColumnDefine から id を取得

        $prefix = $columnType->prefix ?? '';
        $digits = max(1, (int)($columnType->digits ?? 3));
        $revision = $columnType->revision ?? '';
        $isUnique = $columnDefine->unique ?? false;

        $maxNumber = 0;

        $ledgers = Ledger::where('ledger_define_id', $ledgerDefineId)->get();

        foreach ($ledgers as $ledger) {
            $contentValue = $ledger->content[$columnId] ?? null;

            if (is_string($contentValue)) {
                $pattern = '';
                $delimiter = '#';
                $escapedPrefix = preg_quote($prefix, $delimiter);
                $escapedRevision = preg_quote($revision, $delimiter);

                if ($isUnique) {
                    $pattern = $delimiter . '^' . $escapedPrefix . '(\d+).*' . $delimiter;
                } else {
                    $pattern = $delimiter . '^' . $escapedPrefix . '(\d+)' . ($escapedRevision ? $escapedRevision : '') . '$' . $delimiter;
                }

                if (preg_match($pattern, $contentValue, $matches)) {
                    $currentNumber = (int)$matches[1];
                    if ($currentNumber > $maxNumber) {
                        $maxNumber = $currentNumber;
                    }
                }
            }
        }

        $nextNumber = $maxNumber + 1;
        $formattedNumber = str_pad($nextNumber, $digits, '0', STR_PAD_LEFT);

        return $prefix . $formattedNumber . $revision;
    }
}
