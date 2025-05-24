<?php

namespace App\Models\ColumnTypes;

class DateType implements InputType
{
    public function getName(): string
    {
        return 'YMD';
    }

    public function getLabel(): string
    {
        return __('ledger.form.date');
    }

    public function hasOptions(): bool
    {
        // Based on old $useOptionsTypes, 'YMD' does not use options.
        return false;
    }

    public function shouldConvertToJson(): bool
    {
        // Based on old $shouldConvert2JsonTypes, 'YMD' does not convert to JSON.
        return false;
    }

    public function convertToText($value)
    {
        // Logic from ColumnDefine::convertColumnValue2Text for 'YMD'
        if (is_numeric($value)) {
            return date('Y-m-d', (int)$value);
        } elseif (is_string($value)) {
            $time = strtotime($value);
            if ($time === false) {
                return (string)$value; // Or handle error
            }
            return date('Y-m-d', $time);
        }
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        // Logic from ColumnDefine::restoreColumnValueFromText for 'YMD'
        if (empty($value)) {
            return null;
        }
        $time = strtotime($value);
        if ($time === false) {
            // Consider how to handle invalid date strings.
            // For now, returning the original string or null might be options.
            // The old code would likely have resulted in an error or unexpected behavior later.
            return null; 
        }
        // The original code stored dates as timestamps if the column name was 'date_type_column'
        // This seems overly specific and potentially problematic.
        // For now, let's assume we always store the 'Y-m-d' string or a timestamp.
        // Given the name 'YMD', storing as 'Y-m-d' string seems more consistent.
        // However, the original restore logic for 'YMD' directly returned strtotime($value)
        return $time; // Returns Unix timestamp
    }
}
