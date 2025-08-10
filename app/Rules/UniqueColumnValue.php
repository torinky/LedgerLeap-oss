<?php

namespace App\Rules;

use App\Traits\MroongaSearchableColumn;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\Rules\Unique;

class UniqueColumnValue implements ValidationRule
{
    use MroongaSearchableColumn;

    /**
     * @var int 台帳定義ID
     */
    protected int $ledgerDefineId;

    /**
     * @var int contentカラム内のカラムID (0-based index)
     */
    protected int $columnId;

    /**
     * @var int|null 検証時に無視する台帳ID
     */
    protected ?int $ignoreLedgerId;

    /**
     * Create a new rule instance.
     *
     * @param int $ledgerDefineId
     * @param int $columnId
     * @param int|null $ignoreLedgerId
     */
    public function __construct(int $ledgerDefineId, int $columnId, ?int $ignoreLedgerId = null)
    {
        $this->ledgerDefineId = $ledgerDefineId;
        $this->columnId = $columnId;
        $this->ignoreLedgerId = $ignoreLedgerId;
    }

    /**
     * Run the validation rule.
     *
     * このバリデーションは2段階で実行されます。
     * 1. Mroongaの全文検索を使用して、入力値を含む可能性のあるレコードを高速に絞り込みます。
     *    これにより、DB全体をスキャンすることなく、候補を効率的に見つけ出します。
     * 2. 絞り込まれた候補レコードに対し、PHP側でcontentカラムの値を厳密に比較します。
     *    JSONデコードされた値と入力値を `===` で比較し、型まで含めた完全一致を検証します。
     *
     * @param string $attribute
     * @param mixed $value
     * @param \Closure(string): PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 空の値は 'required' ルールでチェックするため、ここでは何もしない
        // '0' や 0 は有効な値として扱う
        if ($value === null || $value === '') {
            return;
        }

        // 1. Mroongaの全文検索で候補を高速に絞り込む
        // トレイトのメソッドを呼び出す際に、必要な引数を渡す
        $potentialMatches = $this->getPotentialMatches($value, $this->columnId, $this->ledgerDefineId, $this->ignoreLedgerId);

        // 候補がなければ重複はない
        if ($potentialMatches->isEmpty()) {
            return;
        }

        // 2. 絞り込んだ候補の中から、PHPで厳密に完全一致をチェックする
        // Ledgerモデルで `content` が `array` にキャストされていることを前提とする
        foreach ($potentialMatches as $ledger) {
            // content カラムが配列であり、対象のキー（インデックス）が存在するか確認
            if (is_array($ledger->content) && array_key_exists($this->columnId, $ledger->content)) {
                $actualValue = $ledger->content[$this->columnId];

                // 入力値とDBの値が完全に一致するかチェック (型も含む)
                if ($actualValue === $value) {
                    // メッセージとしては unique を返す（互換維持）
                    $fail(__('validation.unique'));
                    return;
                }
            }
        }
    }

    /**
     * このクラスと同等条件の Laravel 標準 Unique ルールを生成します。
     * Livewire の 'unique' 失敗ルール名を表面化するために使用します。
     */
    public function toLaravelUniqueRule(): Unique
    {
        $rule = \Illuminate\Validation\Rule::unique('ledgers', "content->{$this->columnId}")
            ->where('ledger_define_id', $this->ledgerDefineId);

        if (!is_null($this->ignoreLedgerId)) {
            $rule = $rule->ignore($this->ignoreLedgerId);
        }

        // ソフトデリートを考慮している場合は null 条件を追加
        if (in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive(\App\Models\Ledger::class), true)) {
            $rule = $rule->whereNull('deleted_at');
        }

        return $rule;
    }

    /**
     * このルールの組み合わせ（カスタム検証 + 標準 unique）を返す。
     * コンポーネント側で array_merge 等でそのまま利用できます。
     *
     * @return array{0:self,1:Unique}
     */
    public function toRules(): array
    {
        return [$this, $this->toLaravelUniqueRule()];
    }


}
