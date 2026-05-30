<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\PhoneNumberType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PhoneNumberType::class)]

class PhoneNumberTypeTest extends TestCase
{
    public function test_phone_number_type_without_options()
    {
        $type = new PhoneNumberType;
        $this->assertEquals('phone', $type->getName());
        $this->assertFalse($type->normalize);
        $this->assertTrue($type->allow_extension);
    }

    public function test_phone_number_validation_rules_relaxed()
    {
        $type = new PhoneNumberType(['allow_extension' => true]);
        $rules = $type->getValidationRules();

        $this->assertContains('regex:/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u', $rules);

        // Validation check (manual regex test)
        $this->assertTrue((bool) preg_match('/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u', '03-1234-5678 (内線123)'));
        $this->assertTrue((bool) preg_match('/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u', '+81 90 1234 5678'));
        $this->assertTrue((bool) preg_match('/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u', '03（1234）5678　内線１２３'));
    }

    public function test_phone_number_validation_rules_strict()
    {
        $type = new PhoneNumberType(['allow_extension' => false]);
        $rules = $type->getValidationRules();

        $this->assertContains('regex:/^[0-9０-９\-－−]+$/u', $rules);

        // Validation check (manual regex test)
        $this->assertTrue((bool) preg_match('/^[0-9０-９\-－−]+$/u', '03-1234-5678'));
        $this->assertFalse((bool) preg_match('/^[0-9０-９\-－−]+$/u', '03-1234-5678 (内線123)'));
        $this->assertTrue((bool) preg_match('/^[0-9０-９\-－−]+$/u', '０３−１２３４−５６７８'));
    }

    public function test_phone_number_normalization()
    {
        $type = new PhoneNumberType(['normalize' => true]);
        $this->assertEquals('0312345678123', $type->convertToText('03-1234-5678 (内線123)'));

        // Full-width digits should be converted and normalized
        $this->assertEquals('09012345678', $type->convertToText('０９０−１２３４ー５６７８'));

        $typeNoNorm = new PhoneNumberType(['normalize' => false]);
        $this->assertEquals('03-1234-5678 (内線123)', $typeNoNorm->convertToText('03-1234-5678 (内線123)'));

        // Full-width digits should be converted even if not normalized
        $this->assertEquals('090-1234-5678', $typeNoNorm->convertToText('０９０-１２３４-５６７８'));
    }

    public function test_phone_number_validation_with_full_width_digits()
    {
        $type = new PhoneNumberType(['allow_extension' => true]);
        $rules = $type->getValidationRules();

        $this->assertTrue((bool) preg_match('/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u', '０９０-１２３４-５６７８'));
        $this->assertTrue((bool) preg_match('/^[0-9０-９+\-＋\(\)（）\s　内線ー－−]+$/u', '090-1234-5678 (内線１２３)'));
    }

    public function test_phone_number_has_options()
    {
        $type = new PhoneNumberType;
        $this->assertTrue($type->hasOptions());
    }

    // ================================================================
    // 未カバーパスの補強テスト
    // ================================================================

    #[Test]
    public function get_name_returns_phone(): void
    {
        $type = new PhoneNumberType;
        $this->assertEquals('phone', $type->getName());
    }

    #[Test]
    public function get_label_returns_non_empty_string(): void
    {
        $type = new PhoneNumberType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function should_convert_to_json_returns_false(): void
    {
        $type = new PhoneNumberType;
        $this->assertFalse($type->shouldConvertToJson());
    }

    #[Test]
    public function is_hidden_returns_false(): void
    {
        $type = new PhoneNumberType;
        $this->assertFalse($type->isHidden());
    }

    #[Test]
    public function restore_from_string_returns_string(): void
    {
        $type = new PhoneNumberType;
        $result = $type->restoreFromString('090-1234-5678');
        $this->assertEquals('090-1234-5678', $result);
    }

    #[Test]
    public function magic_get_returns_normalize_property(): void
    {
        $type = new PhoneNumberType(['normalize' => true]);
        $this->assertTrue($type->normalize);
    }

    #[Test]
    public function magic_get_returns_allow_extension_property(): void
    {
        $type = new PhoneNumberType(['allow_extension' => false]);
        $this->assertFalse($type->allow_extension);
    }

    #[Test]
    public function magic_get_returns_null_for_unknown_property(): void
    {
        $type = new PhoneNumberType;
        $this->assertNull($type->unknown_property);
    }
}
