<?php

namespace App\Models\ColumnTypes;

class PhoneNumberType implements InputType
{
    public function getName(): string
    {
        return 'phone';
    }

    public function getLabel(): string
    {
        // For actual applications, __() should be used for translatable strings.
        // Example: return __('column_types.phone_number');
        return 'Phone Number';
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
        // Basic validation/formatting example: remove non-numeric characters
        return preg_replace('/[^0-9]/', '', (string) $value);
    }

    public function restoreFromString($value)
    {
        // For this example, restore as a simple string.
        // Further formatting or validation could be added here if needed.
        return (string) $value;
    }
}
