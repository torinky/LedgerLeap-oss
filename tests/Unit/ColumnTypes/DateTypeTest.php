<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\DateType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
}
