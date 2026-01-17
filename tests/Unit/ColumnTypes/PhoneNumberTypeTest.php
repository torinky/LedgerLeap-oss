<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\PhoneNumberType;
use Tests\TestCase;

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

        $this->assertContains('regex:/^[0-9+\-\(\)\s内線]+$/u', $rules);

        // Validation check (manual regex test)
        $this->assertTrue((bool) preg_match('/^[0-9+\-\(\)\s内線]+$/u', '03-1234-5678 (内線123)'));
        $this->assertTrue((bool) preg_match('/^[0-9+\-\(\)\s内線]+$/u', '+81 90 1234 5678'));
    }

    public function test_phone_number_validation_rules_strict()
    {
        $type = new PhoneNumberType(['allow_extension' => false]);
        $rules = $type->getValidationRules();

        $this->assertContains('regex:/^[0-9\-]+$/', $rules);

        // Validation check (manual regex test)
        $this->assertTrue((bool) preg_match('/^[0-9\-]+$/', '03-1234-5678'));
        $this->assertFalse((bool) preg_match('/^[0-9\-]+$/', '03-1234-5678 (内線123)'));
    }

    public function test_phone_number_normalization()
    {
        $type = new PhoneNumberType(['normalize' => true]);
        $this->assertEquals('0312345678123', $type->convertToText('03-1234-5678 (内線123)'));

        $typeNoNorm = new PhoneNumberType(['normalize' => false]);
        $this->assertEquals('03-1234-5678 (内線123)', $typeNoNorm->convertToText('03-1234-5678 (内線123)'));
    }

    public function test_phone_number_has_options()
    {
        $type = new PhoneNumberType;
        $this->assertTrue($type->hasOptions());
    }
}
