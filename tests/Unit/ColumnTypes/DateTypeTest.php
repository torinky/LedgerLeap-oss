<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\DateType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DateType::class)]
class DateTypeTest extends TestCase
{
    #[Test]
    public function test_date_type_without_default_offset()
    {
        $dateType = new DateType([]);

        $this->assertNull($dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_today_offset()
    {
        $dateType = new DateType(['default_offset' => '0d']);

        $expectedDate = date('Y-m-d');
        $this->assertEquals($expectedDate, $dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_positive_day_offset()
    {
        $dateType = new DateType(['default_offset' => '1d']);

        $expectedDate = date('Y-m-d', strtotime('+1 day'));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_negative_day_offset()
    {
        $dateType = new DateType(['default_offset' => '-1d']);

        $expectedDate = date('Y-m-d', strtotime('-1 day'));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_week_offset()
    {
        $dateType = new DateType(['default_offset' => '2w']);

        $expectedDate = date('Y-m-d', strtotime('+2 weeks'));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_month_offset()
    {
        $dateType = new DateType(['default_offset' => '1M']);

        $expectedDate = date('Y-m-d', strtotime('+1 month'));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_year_offset()
    {
        $dateType = new DateType(['default_offset' => '1y']);

        $expectedDate = date('Y-m-d', strtotime('+1 year'));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_invalid_offset_format()
    {
        $dateType = new DateType(['default_offset' => 'invalid']);

        $this->assertNull($dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_with_empty_offset()
    {
        $dateType = new DateType(['default_offset' => '']);

        $this->assertNull($dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_preserves_existing_functionality()
    {
        $dateType = new DateType([]);

        // Test getName
        $this->assertEquals('YMD', $dateType->getName());

        // Test hasOptions (changed to true to support default_offset)
        $this->assertTrue($dateType->hasOptions());

        // Test shouldConvertToJson
        $this->assertFalse($dateType->shouldConvertToJson());

        // Test convertToText
        $timestamp = strtotime('2025-10-05');
        $this->assertEquals('2025-10-05', $dateType->convertToText($timestamp));

        // Test restoreFromString
        $restored = $dateType->restoreFromString('2025-10-05');
        $this->assertEquals(strtotime('2025-10-05'), $restored);
    }

    #[Test]
    public function test_date_type_with_options_as_non_array()
    {
        // オプションが配列でない場合も正常に動作することを確認
        $dateType = new DateType('not_an_array');

        $this->assertNull($dateType->getDefaultDate());
    }

    #[Test]
    public function test_date_type_respects_existing_value_without_overwrite()
    {
        $dateType = new DateType([
            'default_offset' => '1d',
            'overwrite_existing' => false,
        ]);

        // 既存値がある場合、デフォルト日付は返さない
        $existingValue = '2025-01-01';
        $this->assertNull($dateType->getDefaultDate($existingValue));
    }

    #[Test]
    public function test_date_type_overwrites_existing_value_when_enabled()
    {
        $dateType = new DateType([
            'default_offset' => '0d',
            'overwrite_existing' => true,
        ]);

        // 既存値があってもデフォルト日付を返す
        $existingValue = '2025-01-01';
        $expectedDate = date('Y-m-d');
        $this->assertEquals($expectedDate, $dateType->getDefaultDate($existingValue));
    }

    #[Test]
    public function test_date_type_returns_default_when_no_existing_value()
    {
        $dateType = new DateType([
            'default_offset' => '2d',
            'overwrite_existing' => false,
        ]);

        // 既存値がない場合、デフォルト日付を返す
        $expectedDate = date('Y-m-d', strtotime('+2 days'));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate(null));
        $this->assertEquals($expectedDate, $dateType->getDefaultDate(''));
    }

    #[Test]
    public function test_date_type_with_empty_offset_returns_null_regardless_of_existing_value()
    {
        $dateType = new DateType([
            'default_offset' => '',
            'overwrite_existing' => true,
        ]);

        // オフセットが空欄の場合は常にnull
        $this->assertNull($dateType->getDefaultDate());
        $this->assertNull($dateType->getDefaultDate('2025-01-01'));
    }

    #[Test]
    public function test_date_type_with_full_width_digits()
    {
        $dateType = new DateType([]);

        // Full-width digits should be restored correctly (converted to half-width then parsed)
        $fullWidth = '２０２６−０１−１７';
        $restored = $dateType->restoreFromString($fullWidth);
        $this->assertEquals(strtotime('2026-01-17'), $restored);
    }

    #[Test]
    public function test_ymdhm_format_conversion()
    {
        $dateType = new DateType([], 'YMDHM');

        $now = time();
        $dateStr = date('Y-m-d H:i', $now);

        // Convert to text
        $text = $dateType->convertToText($now);
        $this->assertEquals($dateStr, $text);

        // Restore from string
        $restored = $dateType->restoreFromString($text);
        $this->assertEquals(strtotime($dateStr), $restored);
    }

    // ================================================================
    // 未カバーパスの補強テスト
    // ================================================================

    #[Test]
    public function test_get_validation_rules_for_ymd()
    {
        $dateType = new DateType([], 'YMD');
        $rules = $dateType->getValidationRules();
        $this->assertEquals(['date_format:Y-m-d'], $rules);
    }

    #[Test]
    public function test_get_validation_rules_for_ymdhm()
    {
        $dateType = new DateType([], 'YMDHM');
        $rules = $dateType->getValidationRules();
        $this->assertEquals(['date_format:Y-m-d H:i'], $rules);
    }

    #[Test]
    public function test_get_label_for_ymdhm()
    {
        $dateType = new DateType([], 'YMDHM');
        // datetime ラベルが返る（翻訳キー確認）
        $label = $dateType->getLabel();
        $this->assertNotEmpty($label);
    }

    #[Test]
    public function test_get_label_for_ymd()
    {
        $dateType = new DateType([], 'YMD');
        $label = $dateType->getLabel();
        $this->assertNotEmpty($label);
    }

    #[Test]
    public function test_is_hidden_returns_true_when_default_offset_set()
    {
        $dateType = new DateType(['default_offset' => '1d'], 'YMD');
        $this->assertTrue($dateType->isHidden());
    }

    #[Test]
    public function test_is_hidden_returns_false_when_no_default_offset()
    {
        $dateType = new DateType([], 'YMD');
        $this->assertFalse($dateType->isHidden());
    }

    #[Test]
    public function test_date_type_with_hour_offset()
    {
        $dateType = new DateType(['default_offset' => '2h'], 'YMDHM');
        $result = $dateType->getDefaultDate();
        $expected = (new \DateTime)->modify('2 hours')->format('Y-m-d H:i');
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function test_date_type_with_minute_offset()
    {
        $dateType = new DateType(['default_offset' => '30m'], 'YMDHM');
        $result = $dateType->getDefaultDate();
        $expected = (new \DateTime)->modify('30 minutes')->format('Y-m-d H:i');
        $this->assertEquals($expected, $result);
    }

    #[Test]
    public function test_get_default_date_returns_ymdhm_format_when_type_is_ymdhm()
    {
        $dateType = new DateType(['default_offset' => '0d'], 'YMDHM');
        $result = $dateType->getDefaultDate();
        // YMDHM 形式 (Y-m-d H:i)
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $result);
    }

    #[Test]
    public function test_convert_to_text_with_string_input()
    {
        $dateType = new DateType([], 'YMD');
        $result = $dateType->convertToText('2026-01-15');
        $this->assertEquals('2026-01-15', $result);
    }

    #[Test]
    public function test_convert_to_text_with_invalid_string_returns_string()
    {
        $dateType = new DateType([], 'YMD');
        $result = $dateType->convertToText('not-a-date');
        $this->assertIsString($result);
    }

    #[Test]
    public function test_restore_from_string_with_empty_returns_null()
    {
        $dateType = new DateType([], 'YMD');
        $this->assertNull($dateType->restoreFromString(''));
        $this->assertNull($dateType->restoreFromString(null));
    }

    #[Test]
    public function test_restore_from_string_with_invalid_date_returns_null()
    {
        $dateType = new DateType([], 'YMD');
        $result = $dateType->restoreFromString('not-a-valid-date-xyz');
        $this->assertNull($result);
    }

    #[Test]
    public function test_magic_get_returns_null_for_unknown_property()
    {
        $dateType = new DateType([], 'YMD');
        $this->assertNull($dateType->unknown_property);
    }

    #[Test]
    public function test_has_options_returns_true()
    {
        $dateType = new DateType([], 'YMD');
        $this->assertTrue($dateType->hasOptions());
    }

    #[Test]
    public function test_should_convert_to_json_returns_false()
    {
        $dateType = new DateType([], 'YMD');
        $this->assertFalse($dateType->shouldConvertToJson());
    }
}
