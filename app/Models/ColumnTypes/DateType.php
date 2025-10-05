<?php

namespace App\Models\ColumnTypes;

class DateType implements InputType
{
    private ?string $defaultOffset = null;

    public function __construct($options = [])
    {
        // optionsがarrayの場合、default_offsetを取得
        if (is_array($options) && isset($options['default_offset'])) {
            $this->defaultOffset = $options['default_offset'];
        }
    }

    public function getName(): string
    {
        return 'YMD';
    }

    public function getLabel(): string
    {
        return __('ledger.form.date');
    }

    public function hasOptions(): bool
    {
        // Based on old $useOptionsTypes, 'YMD' does not use options.
        return false;
    }

    public function shouldConvertToJson(): bool
    {
        // Based on old $shouldConvert2JsonTypes, 'YMD' does not convert to JSON.
        return false;
    }

    /**
     * デフォルト日付を計算
     * 
     * @return string|null Y-m-d形式の日付、またはnull
     */
    public function getDefaultDate(): ?string
    {
        if (empty($this->defaultOffset)) {
            return null;
        }

        return $this->calculateDateFromOffset($this->defaultOffset);
    }

    /**
     * オフセット文字列から日付を計算
     * 
     * 形式: "1d" (1日後), "2M" (2ヶ月後), "-1w" (1週間前), "0d" (今日)
     * 
     * @param string $offset オフセット文字列
     * @return string|null Y-m-d形式の日付
     */
    private function calculateDateFromOffset(string $offset): ?string
    {
        // オフセット形式: 数値 + 単位 (d=日, w=週, M=月, y=年)
        if (!preg_match('/^([+-]?\d+)([dwMy])$/', $offset, $matches)) {
            return null;
        }

        $amount = (int) $matches[1];
        $unit = $matches[2];

        try {
            $date = new \DateTime();
            
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
                default:
                    return null;
            }

            return $date->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function convertToText($value)
    {
        // Logic from ColumnDefine::convertColumnValue2Text for 'YMD'
        if (is_numeric($value)) {
            return date('Y-m-d', (int) $value);
        } elseif (is_string($value)) {
            $time = strtotime($value);
            if ($time === false) {
                return (string) $value; // Or handle error
            }

            return date('Y-m-d', $time);
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
        return ['date_format:Y-m-d'];
    }
}
