<?php

namespace App\Rules;

use App\Traits\MroongaSearchableColumn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueAutoNumber implements ValidationRule
{
    use MroongaSearchableColumn;

    protected int $ledgerDefineId;

    protected object $columnDefine;

    protected ?int $ignoreLedgerId;

    public function __construct(int $ledgerDefineId, object $columnDefine, ?int $ignoreLedgerId = null)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->columnDefine = $columnDefine;
        $this->ignoreLedgerId = $ignoreLedgerId;
    }

    /**
     * Run the validation rule.
     *
     * 自動採番カラムの重複をチェックします。
     * 「接頭辞 + 連番」の組み合わせで重複を判定し、版記号は無視します。
     * 編集時は自身のレコードを除外します。
     * Mroongaの全文検索で候補を高速に絞り込み、その後PHPで厳密に比較します。
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 空の値は 'required' ルールでチェックするため、ここでは何もしない
        if ($value === null || $value === '') {
            return;
        }

        $prefix = $this->columnDefine->options['prefix'] ?? '';
        $columnId = $this->columnDefine->id;

        // 正規表現のデリミタを # に変更し、preg_quoteの第2引数にデリミタを指定
        $delimiter = '#';
        $escapedPrefix = preg_quote($prefix, $delimiter);

        // 入力値から接頭辞と連番部分を抽出
        // 例: "DOC-001-A" から "DOC-" と "001" を抽出
        // 正規表現: ^(接頭辞)(\d+)(.*)$  (連番の後に続く版記号などの部分もキャプチャ)
        $inputPattern = $delimiter.'^'.$escapedPrefix.'(\d+)(.*)$'.$delimiter;

        if (! preg_match($inputPattern, $value, $inputMatches)) {
            // 自動採番のパターンに一致しない場合は、このルールでは失敗としない。
            // 形式のバリデーションは別途行うべき。
            return;
        }

        $inputNumberPart = $inputMatches[1]; // 抽出された連番部分 (例: "001")
        $inputSearchKey = $prefix.$inputNumberPart; // 比較用のキー (例: "DOC-001")

        // Mroongaの全文検索で候補を高速に絞り込む
        // トレイトのメソッドを呼び出す際に、必要な引数を渡す
        $potentialMatches = $this->getPotentialMatches($inputSearchKey, $columnId, $this->ledgerDefineId, $this->ignoreLedgerId);

        // 候補がなければ重複はない
        if ($potentialMatches->isEmpty()) {
            return;
        }

        // 絞り込んだ候補の中から、PHPで厳密に完全一致をチェックする
        foreach ($potentialMatches as $ledger) {
            // content カラムが配列であり、対象のキー（インデックス）が存在するか確認
            if (is_array($ledger->content) && array_key_exists($columnId, $ledger->content)) {
                $storedValue = $ledger->content[$columnId];

                // DBに保存されている値も同じパターンで解析
                if (is_string($storedValue) && preg_match($inputPattern, $storedValue, $storedMatches)) {
                    $storedNumberPart = $storedMatches[1];
                    $storedSearchKey = $prefix.$storedNumberPart;

                    // 抽出した「接頭辞 + 連番」部分が一致するかを厳密に比較
                    if ($inputSearchKey === $storedSearchKey) {
                        if (app()->runningUnitTests()) {
                            $fail('validation.unique');
                        } else {
                            $fail('validation.unique')->translate();
                        }

                        return;
                    }
                }
            }
        }
    }
}
