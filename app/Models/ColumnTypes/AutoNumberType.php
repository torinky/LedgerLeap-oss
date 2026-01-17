<?php

namespace App\Models\ColumnTypes;

class AutoNumberType implements InputType
{
    public ?string $prefix;

    public ?int $digits;

    public ?string $revision;

    public function __construct(array $options = [])
    {
        $this->prefix = $options['prefix'] ?? null;
        $this->digits = $options['digits'] ?? null;
        $this->revision = $options['revision'] ?? null;
    }

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
        return $value;
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getValidationRules(): array
    {
        $prefixLength = strlen($this->prefix ?? '');
        $digitsLength = (int) ($this->digits ?? 0);
        $minAutoNumberLength = $prefixLength + $digitsLength;

        return ['string', 'min:'.$minAutoNumberLength];
    }
}
