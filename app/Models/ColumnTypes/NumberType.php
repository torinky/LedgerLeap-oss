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
        // 全角数字を半角に変換
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');

        return $value;
    }

    public function restoreFromString($value)
    {
        // 全角数字を半角に変換
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $value;
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getValidationRules(): array
    {
        $rules = ['numeric'];

        if (! is_null($this->min)) {
            $rules[] = "min:{$this->min}";
        }

        if (! is_null($this->max)) {
            $rules[] = "max:{$this->max}";
        }

        if (! is_null($this->step) && $this->step > 0) {
            $rules[] = "multiple_of:{$this->step}";
        }

        return $rules;
    }
}
