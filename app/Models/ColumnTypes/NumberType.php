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
    public function setMin($min): void
    {
        $this->min = $min;
    }

    public function setMax($max): void
    {
        $this->max = $max;
    }

    public function setStep($step): void
    {
        $this->step = $step;
    }

    public function setUnit($unit): void
    {
        $this->unit = $unit;
    }

}
