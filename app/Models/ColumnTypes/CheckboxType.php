<?php

namespace App\Models\ColumnTypes;

class CheckboxType implements InputType
{
    public function getName(): string
    {
        return 'chk';
    }

    public function getLabel(): string
    {
        return __('ledger.form.check');
    }

    public function hasOptions(): bool
    {
        // Based on old $useOptionsTypes, 'chk' uses options.
        return true;
    }

    public function shouldConvertToJson(): bool
    {
        // Based on old $shouldConvert2JsonTypes, 'chk' converts to JSON.
        return true;
    }

    public function convertToText($value)
    {
        if ($this->shouldConvertToJson()) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        if ($this->shouldConvertToJson()) {
            return json_decode($value, true);
        }
        return $value;
    }

    public function getValidationRules(): array
    {
        return ['array'];
    }
}
