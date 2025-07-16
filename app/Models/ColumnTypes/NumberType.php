<?php

namespace App\Models\ColumnTypes;

class NumberType implements InputType
{
    public ?float $min;
    public ?float $max;
    public ?float $step;
    public ?string $unit;

    public function __construct(array $options = [])
    {
        $this->min = $options['min'] ?? null;
        $this->max = $options['max'] ?? null;
        $this->step = $options['step'] ?? null;
        $this->unit = $options['unit'] ?? null;
    }

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

    public function getValidationRules(): array
    {
        $rules = ['numeric'];
        if (isset($this->min)) {
            $rules[] = 'min:'.$this->min;
        }
        if (isset($this->max)) {
            $rules[] = 'max:'.$this->max;
        }
        if (isset($this->step)) {
            $rules[] = 'multiple_of:'.$this->step;
        }
        return $rules;
    }
}