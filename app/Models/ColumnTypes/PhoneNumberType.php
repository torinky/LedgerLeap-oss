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
        // 全角数字を半角に変換
        $value = mb_convert_kana((string) $value, 'n', 'UTF-8');

        if ($this->normalize) {
            // 数字のみ抽出
            return preg_replace('/[^0-9]/', '', $value);
        }

        // そのまま返す（または最小限のトリム）
        return trim($value);
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
            // 全角数字や全角記号も入力される可能性があるため、利便性のために許容する
            return ['string', 'max:50', 'regex:/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u'];
        }

        // 従来の厳格なルール（数字とハイフンのみ、全角数字・ハイフンも許容）
        return ['string', 'regex:/^[0-9０-９\-－−]+$/u'];
    }
}
