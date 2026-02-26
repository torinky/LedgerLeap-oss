<?php

namespace App\Models\ColumnTypes;

class TextType implements InputType
{
    public function getName(): string
    {
        return 'text';
    }

    public function getLabel(): string
    {
        return __('ledger.form.text');
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
        return $value;
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getValidationRules(): array
    {
        return ['string'];
    }
}
