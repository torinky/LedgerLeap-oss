<?php

namespace App\Models\ColumnTypes;

class FilesType implements InputType
{
    public function getName(): string
    {
        return 'files';
    }

    public function getLabel(): string
    {
        return __('ledger.form.files');
    }

    public function hasOptions(): bool
    {
        // Based on old $useOptionsTypes, 'files' does not use options.
        return false;
    }

    public function shouldConvertToJson(): bool
    {
        // Based on old $shouldConvert2JsonTypes, 'files' converts to JSON.
        return true;
    }

    public function convertToText($value)
    {
        if ($this->shouldConvertToJson()) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string) $value;
    }

    public function restoreFromString($value)
    {
        if ($this->shouldConvertToJson()) {
            // Logic from ColumnDefine::restoreColumnValueFromText for 'files'
            if (is_string($value)) {
                $files = json_decode($value, true);
                // The original code had a check for $files === $value, which seems redundant
                // if json_decode fails, it returns null. If it succeeds, it's an array.
                // A string input that is also valid JSON and identical to itself after decoding is unlikely.
                // Let's simplify to just returning the decoded value.
                return $files;
            } elseif (is_array($value)) { // Already an array, no need to decode
                return $value;
            }
            return null; // Or handle error appropriately
        }
        return $value;
    }

    public function getValidationRules(): array
    {
        return ['array'];
    }
}
