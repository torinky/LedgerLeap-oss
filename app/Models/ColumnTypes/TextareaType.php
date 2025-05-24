<?php

namespace App\Models\ColumnTypes;

class TextareaType implements InputType
{
    public function getName(): string
    {
        return 'textarea';
    }

    public function getLabel(): string
    {
        return __('ledger.form.textarea');
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
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        return (string) $value;
    }
}
