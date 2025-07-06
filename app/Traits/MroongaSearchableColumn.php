<?php

namespace App\Traits;

use App\Models\Ledger;
use Closure;
use Illuminate\Database\Eloquent\Collection;

trait MroongaSearchableColumn
{
    /**
     * Mroongaを使用して、重複の可能性があるLedgerレコードの候補を取得します。
     *
     * @param mixed $value 検索する値
     * @param int $columnId 対象のカラムID (content JSON内のキー)
     * @param int $ledgerDefineId 台帳定義ID
     * @param int|null $ignoreLedgerId 検証時に無視する台帳ID
     * @return Collection
     */
    private function getPotentialMatches(mixed $value, int $columnId, int $ledgerDefineId, ?int $ignoreLedgerId = null): Collection
    {
        $query = Ledger::where('ledger_define_id', $ledgerDefineId);

        // Mroongaの全文検索では、検索語が配列の場合はJSON文字列に変換する
        $searchValue = is_array($value) ? json_encode($value) : $value;

        // Mroongaのブーリアンモードでフレーズ検索 `+"..."` を使うため、`"` をエスケープする
        $escapedSearchValue = addslashes($searchValue);

        // Mroongaのカラム指定検索 (`*W<N>`) を使用して、特定のカラムインデックスを対象にする
        // カラムインデックスは1ベースなので +1 する
        $mroongaColumnIndex = $columnId + 1;

        $query->whereRaw(
            "match(`content`) against ('*W{$mroongaColumnIndex} +\"{$escapedSearchValue}\"' IN BOOLEAN MODE)"
        );

        // 更新時には自分自身のレコードを重複チェックの対象から除外する
        if ($ignoreLedgerId) {
            $query->where('id', '!=', $ignoreLedgerId);
        }

        // 厳密な比較のために 'id' と 'content' カラムを取得する
        return $query->get(['id', 'content']);
    }
}