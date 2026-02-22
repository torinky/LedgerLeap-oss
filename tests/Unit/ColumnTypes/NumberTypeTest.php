<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\NumberType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * NumberType のテスト
 *
 * getValidationRules() の min/max/step/unit オプション、
 * convertToText()、restoreFromString() を検証する。
 */
#[CoversClass(NumberType::class)]
class NumberTypeTest extends TestCase
{
    // ================================================================
    // 基本プロパティ
    // ================================================================

    #[Test]
    public function get_name_returns_number(): void
    {
        $type = new NumberType;
        $this->assertEquals('number', $type->getName());
    }

    #[Test]
    public function get_label_returns_non_empty_string(): void
    {
        $type = new NumberType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function has_options_returns_true(): void
    {
        $type = new NumberType;
        $this->assertTrue($type->hasOptions());
    }

    #[Test]
    public function should_convert_to_json_returns_false(): void
    {
        $type = new NumberType;
        $this->assertFalse($type->shouldConvertToJson());
    }

    #[Test]
    public function is_hidden_returns_false(): void
    {
        $type = new NumberType;
        $this->assertFalse($type->isHidden());
    }

    // ================================================================
    // コンストラクタのオプション
    // ================================================================

    #[Test]
    public function constructor_sets_options(): void
    {
        $type = new NumberType([
            'min' => 0.0,
            'max' => 100.0,
            'step' => 0.5,
            'unit' => 'kg',
        ]);

        $this->assertEquals(0.0, $type->min);
        $this->assertEquals(100.0, $type->max);
        $this->assertEquals(0.5, $type->step);
        $this->assertEquals('kg', $type->unit);
    }

    #[Test]
    public function constructor_defaults_to_null(): void
    {
        $type = new NumberType;

        $this->assertNull($type->min);
        $this->assertNull($type->max);
        $this->assertNull($type->step);
        $this->assertNull($type->unit);
    }

    // ================================================================
    // getValidationRules
    // ================================================================

    #[Test]
    public function get_validation_rules_returns_only_numeric_when_no_options(): void
    {
        $type = new NumberType;
        $this->assertEquals(['numeric'], $type->getValidationRules());
    }

    #[Test]
    public function get_validation_rules_includes_min_when_set(): void
    {
        $type = new NumberType(['min' => 10.0]);
        $rules = $type->getValidationRules();
        $this->assertContains('min:10', $rules);
    }

    #[Test]
    public function get_validation_rules_includes_max_when_set(): void
    {
        $type = new NumberType(['max' => 100.0]);
        $rules = $type->getValidationRules();
        $this->assertContains('max:100', $rules);
    }

    #[Test]
    public function get_validation_rules_includes_multiple_of_when_step_positive(): void
    {
        $type = new NumberType(['step' => 2.0]);
        $rules = $type->getValidationRules();
        $this->assertContains('multiple_of:2', $rules);
    }

    #[Test]
    public function get_validation_rules_excludes_multiple_of_when_step_zero(): void
    {
        $type = new NumberType(['step' => 0]);
        $rules = $type->getValidationRules();
        $hasMultipleOf = collect($rules)->contains(fn ($r) => str_starts_with($r, 'multiple_of:'));
        $this->assertFalse($hasMultipleOf);
    }

    #[Test]
    public function get_validation_rules_includes_all_when_all_options_set(): void
    {
        $type = new NumberType(['min' => 1.0, 'max' => 50.0, 'step' => 5.0]);
        $rules = $type->getValidationRules();

        $this->assertContains('numeric', $rules);
        $this->assertContains('min:1', $rules);
        $this->assertContains('max:50', $rules);
        $this->assertContains('multiple_of:5', $rules);
    }

    // ================================================================
    // convertToText / restoreFromString
    // ================================================================

    #[Test]
    public function convert_to_text_converts_fullwidth_digits(): void
    {
        $type = new NumberType;
        $result = $type->convertToText('１２３');
        $this->assertEquals('123', $result);
    }

    #[Test]
    public function restore_from_string_returns_float_for_numeric(): void
    {
        $type = new NumberType;
        $result = $type->restoreFromString('42.5');
        $this->assertEquals(42.5, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function restore_from_string_returns_string_for_non_numeric(): void
    {
        $type = new NumberType;
        $result = $type->restoreFromString('not-a-number');
        $this->assertIsString($result);
    }

    #[Test]
    public function restore_from_string_converts_fullwidth_digits(): void
    {
        $type = new NumberType;
        $result = $type->restoreFromString('２５');
        $this->assertEquals(25.0, $result);
    }
}
