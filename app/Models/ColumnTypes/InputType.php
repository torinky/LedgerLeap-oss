<?php

namespace App\Models\ColumnTypes;

interface InputType
{
    public function getName(): string; // e.g., 'text', 'select'

    public function getLabel(): string; // e.g., __('ledger.form.text')

    public function hasOptions(): bool;

    public function shouldConvertToJson(): bool;

    public function convertToText($value);

    public function restoreFromString($value);

    public function getValidationRules(): array;
}
