<?php

namespace App\Models\ColumnTypes;

class DateType implements InputType
{
    private ?string $defaultOffset = null;

    private bool $overwriteExisting = false;

    private string $typeIdentifier;

    public function __construct($options = [], ?string $typeIdentifier = 'YMD')
    {
        $this->typeIdentifier = $typeIdentifier;

        // optionsがarrayの場合、default_offset と overwrite_existing を取得
        if (is_array($options)) {
            $this->defaultOffset = $options['default_offset'] ?? null;
            $this->overwriteExisting = (bool) ($options['overwrite_existing'] ?? false);
        }
    }

    /**
     * Magic getter for property access (supports snake_case)
     */
    public function __get($name)
    {
        if ($name === 'default_offset') {
            return $this->defaultOffset;
        }
        if ($name === 'overwrite_existing') {
            return $this->overwriteExisting;
        }

        return null;
    }

    public function getName(): string
    {
        return $this->typeIdentifier;
    }

    public function getLabel(): string
    {
        return $this->typeIdentifier === 'YMDHM'
            ? __('ledger.form.datetime')
            : __('ledger.form.date');
    }

    public function hasOptions(): bool
    {
        // DateType now uses options for default_offset and overwrite_existing configuration
        return true;
    }

    public function shouldConvertToJson(): bool
    {
        return false;
    }

    public function isHidden(): bool
    {
        // デフォルトオフセットが設定されている場合は、自動入力項目とみなしてUI上では非表示とする
        return ! empty($this->defaultOffset);
    }

    /**
     * デフォルト日付を計算
     *
     * @param  mixed  $existingValue  既存の値（再編集の場合）
     * @return string|null Y-m-d形式の日付、またはnull
     */
    public function getDefaultDate($existingValue = null): ?string
    {
        // オフセットが空欄の場合はnullを返す
        if (empty($this->defaultOffset)) {
            return null;
        }

        // 既存値があり、上書き設定がfalseの場合は既存値を優先
        if (! empty($existingValue) && ! $this->overwriteExisting) {
            return null; // 既存値を変更しない
        }

        return $this->calculateDateFromOffset($this->defaultOffset);
    }

    /**
     * オフセット文字列から日付を計算
     *
     * 形式: "1d" (1日後), "2M" (2ヶ月後), "-1w" (1週間前), "0d" (今日)
     *
     * @param  string  $offset  オフセット文字列
     * @return string|null Y-m-d形式の日付
     */
    private function calculateDateFromOffset(string $offset): ?string
    {
        // オフセット形式: 数値 + 単位 (d=日, w=週, M=月, y=年, h=時, m=分)
        if (! preg_match('/^([+-]?\d+)([dwMyhm])$/', $offset, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        $unit = $matches[2];

        try {
            $date = new \DateTime;

            switch ($unit) {
                case 'd': // 日
                    $date->modify("{$amount} days");
                    break;
                case 'w': // 週
                    $date->modify("{$amount} weeks");
                    break;
                case 'M': // 月
                    $date->modify("{$amount} months");
                    break;
                case 'y': // 年
                    $date->modify("{$amount} years");
                    break;
                case 'h': // 時
                    $date->modify("{$amount} hours");
                    break;
                case 'm': // 分
                    $date->modify("{$amount} minutes");
                    break;
                default:
                    return null;
            }

            return $this->typeIdentifier === 'YMDHM'
                ? $date->format('Y-m-d H:i')
                : $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function convertToText($value)
    {
        $format = $this->typeIdentifier === 'YMDHM' ? 'Y-m-d H:i' : 'Y-m-d';

        if (is_numeric($value)) {
            return date($format, (int) $value);
        } elseif (is_string($value)) {
            $time = strtotime($value);
            if ($time === false) {
                return (string) $value;
            }

            return date($format, $time);
        }

        return (string) $value;
    }

    public function restoreFromString($value)
    {
        // Logic from ColumnDefine::restoreColumnValueFromText for 'YMD'
        if (empty($value)) {
            return null;
        }
        $time = strtotime($value);
        if ($time === false) {
            // Consider how to handle invalid date strings.
            // For now, returning the original string or null might be options.
            // The old code would likely have resulted in an error or unexpected behavior later.
            return null;
        }

        // The original code stored dates as timestamps if the column name was 'date_type_column'
        // This seems overly specific and potentially problematic.
        // For now, let's assume we always store the 'Y-m-d' string or a timestamp.
        // Given the name 'YMD', storing as 'Y-m-d' string seems more consistent.
        // However, the original restore logic for 'YMD' directly returned strtotime($value)
        return $time; // Returns Unix timestamp
    }

    public function getValidationRules(): array
    {
        return $this->typeIdentifier === 'YMDHM'
            ? ['date_format:Y-m-d H:i']
            : ['date_format:Y-m-d'];
    }
}
