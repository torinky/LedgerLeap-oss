<?php

namespace App\Rules;

use App\Models\Ledger;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueColumnValue implements ValidationRule
{
    protected $ledgerDefineId;
    protected $columnId;
    protected $ignoreLedgerId;

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
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // 空の値は 'required' ルールでチェックする
        }

        $query = Ledger::where('ledger_define_id', $this->ledgerDefineId);

        // Use whereRaw for precise JSON value matching, assuming content is a JSON array
        $mroongaColumnCount = $this->columnId + 1;

        // Handle array values by converting them to string (e.g., JSON string)
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $query->whereRaw("match(`content`) against ('*W" . $mroongaColumnCount . " +\"" . $value . "\"' IN BOOLEAN MODE)");

        if ($this->ignoreLedgerId) {
            $query->where('id', '!=', $this->ignoreLedgerId);
        }

        if ($query->exists()) {
            $fail('validation.unique')->translate();
        }
    }
}