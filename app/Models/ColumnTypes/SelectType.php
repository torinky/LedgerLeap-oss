<?php

namespace App\Models\ColumnTypes;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class SelectType implements InputType
{
    public array $options;

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function getName(): string
    {
        return 'select';
    }

    public function getLabel(): string
    {
        return __('ledger.form.select');
    }

    public function hasOptions(): bool
    {
        // Based on old $useOptionsTypes, 'select' uses options.
        return true;
    }

    public function shouldConvertToJson(): bool
    {
        // Based on old $shouldConvert2JsonTypes, 'select' does not convert to JSON.
        return false;
    }

    public function convertToText($value)
    {
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        return (string) $value;
    }

    public function getValidationRules(): array
    {
        return ['string', Rule::in($this->options)];
    }
}
