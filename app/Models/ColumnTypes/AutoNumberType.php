<?php

namespace App\Models\ColumnTypes;

class AutoNumberType implements InputType
{
    public function getName(): string
    {
        return 'auto_number';
    }

    public function getLabel(): string
    {
        return __('ledger.form.auto_number');
    }

    public function hasOptions(): bool
    {
        return true;
    }

    public function shouldConvertToJson(): bool
    {
        return false;
    }

    public function convertToText($value)
    {
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        if (is_numeric($value)) {
            return $value + 0; // Converts to int or float
        }
        return $value;
    }
}