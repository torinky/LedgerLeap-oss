<?php

namespace App\Models\ColumnTypes;

class PhoneNumberType implements InputType
{
    private bool $normalize = false;

    private bool $allowExtension = true;

    public function __construct(array $options = [])
    {
        $this->normalize = (bool) ($options['normalize'] ?? false);
        $this->allowExtension = (bool) ($options['allow_extension'] ?? true);
    }

    /**
     * Magic getter for property access (supports snake_case)
     */
    public function __get($name)
    {
        if ($name === 'normalize') {
            return $this->normalize;
        }
        if ($name === 'allow_extension') {
            return $this->allowExtension;
        }

        return null;
    }

    public function getName(): string
    {
        return 'phone';
    }

    public function getLabel(): string
    {
        return __('ledger.form.phone');
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
        if ($this->normalize) {
            // 数字のみ抽出
            return preg_replace('/[^0-9]/', '', (string) $value);
        }

        // そのまま返す（または最小限のトリム）
        return trim((string) $value);
    }

    public function restoreFromString($value)
    {
        return (string) $value;
    }

    public function isHidden(): bool
    {
        return false;
    }

    public function getValidationRules(): array
    {
        if ($this->allowExtension) {
            // 内線やカッコ、スペース、+ を許容する緩やかなルール
            // 文字列として最低限の長さを持ち、電話番号として不適切な文字が含まれていないこと
            return ['string', 'max:50', 'regex:/^[0-9+\-\(\)\s内線]+$/u'];
        }

        // 従来の厳格なルール（数字とハイフンのみ）
        return ['string', 'regex:/^[0-9\-]+$/'];
    }
}
