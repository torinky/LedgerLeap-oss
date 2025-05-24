<?php

namespace App\Models\ColumnTypes;

class NumberType implements InputType
{
    public function getName(): string
    {
        return 'number';
    }

    public function getLabel(): string
    {
        return __('ledger.form.number');
    }

    public function hasOptions(): bool
    {
        return false;
    }

    public function shouldConvertToJson(): bool
    {
        return false;
    }

    public function convertToText($value)
    {
        // Based on current ColumnDefine, numbers are stored as is.
        // Conversion to string might happen at a higher level or implicitly.
        // For consistency, let's cast to string here.
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        // Based on current ColumnDefine, numbers are stored as is.
        // If the string is numeric, cast to int/float.
        // This might need adjustment based on how it's used.
        // The old code didn't have specific restore logic for numbers beyond general handling.
        if (is_numeric($value)) {
            return $value + 0; // Converts to int or float
        }
        return $value; // Or handle error/return null
    }
}
