<?php

namespace App\Models\ColumnTypes;

class CheckboxType implements InputType
{
    public function getName(): string
    {
        return 'chk';
    }

    public function getLabel(): string
    {
        return __('ledger.form.check');
    }

    public function hasOptions(): bool
    {
        // Based on old $useOptionsTypes, 'chk' uses options.
        return true;
    }

    public function shouldConvertToJson(): bool
    {
        // Based on old $shouldConvert2JsonTypes, 'chk' converts to JSON.
        return true;
    }

    public function convertToText($value)
    {
        // プロジェクト規約（二重エンコード厳禁）に基づき、配列の場合はそのまま返す。
        // キャスト (AsColumnArrayJson) がシリアライズを担当するため。
        if (is_array($value)) {
            return $value;
        }

        return (string) $value;
    }

    public function restoreFromString($value)
    {
        if (empty($value)) {
            return [];
        }

        if (is_string($value) && (str_starts_with($value, '[') || str_starts_with($value, '{'))) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return [$value];
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return [$value];
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getValidationRules(): array
    {
        return ['array'];
    }
}
