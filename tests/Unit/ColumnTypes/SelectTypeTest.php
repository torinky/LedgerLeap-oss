<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\SelectType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SelectType のテスト
 *
 * getValidationRules() の Rule::in() 動作、convertToText()、restoreFromString() を検証。
 */
#[CoversClass(SelectType::class)]
class SelectTypeTest extends TestCase
{
    // ================================================================
    // 基本プロパティ
    // ================================================================

    #[Test]
    public function get_name_returns_select(): void
    {
        $type = new SelectType;
        $this->assertEquals('select', $type->getName());
    }

    #[Test]
    public function get_label_returns_non_empty(): void
    {
        $type = new SelectType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function has_options_returns_true(): void
    {
        $type = new SelectType;
        $this->assertTrue($type->hasOptions());
    }

    #[Test]
    public function should_convert_to_json_returns_false(): void
    {
        $type = new SelectType;
        $this->assertFalse($type->shouldConvertToJson());
    }

    #[Test]
    public function is_hidden_returns_false(): void
    {
        $type = new SelectType;
        $this->assertFalse($type->isHidden());
    }

    // ================================================================
    // コンストラクタ
    // ================================================================

    #[Test]
    public function constructor_sets_options(): void
    {
        $options = ['opt1', 'opt2', 'opt3'];
        $type = new SelectType($options);
        $this->assertEquals($options, $type->options);
    }

    #[Test]
    public function constructor_defaults_to_empty_array(): void
    {
        $type = new SelectType;
        $this->assertEquals([], $type->options);
    }

    // ================================================================
    // getValidationRules
    // ================================================================

    #[Test]
    public function get_validation_rules_includes_string_and_in_rule(): void
    {
        $type = new SelectType(['alpha', 'beta', 'gamma']);
        $rules = $type->getValidationRules();

        $this->assertContains('string', $rules);
        // Rule::in() オブジェクトが含まれること
        $hasInRule = collect($rules)->contains(
            fn ($r) => $r instanceof \Illuminate\Validation\Rules\In
        );
        $this->assertTrue($hasInRule, 'Rule::in() should be in validation rules');
    }

    #[Test]
    public function get_validation_rules_with_empty_options(): void
    {
        $type = new SelectType([]);
        $rules = $type->getValidationRules();
        $this->assertContains('string', $rules);
    }

    // ================================================================
    // convertToText / restoreFromString
    // ================================================================

    #[Test]
    public function convert_to_text_returns_string(): void
    {
        $type = new SelectType(['opt1', 'opt2']);
        $this->assertEquals('opt1', $type->convertToText('opt1'));
        $this->assertEquals('123', $type->convertToText(123));
    }

    #[Test]
    public function restore_from_string_returns_value_as_is(): void
    {
        $type = new SelectType(['opt1', 'opt2']);
        $this->assertEquals('opt1', $type->restoreFromString('opt1'));
        $this->assertNull($type->restoreFromString(null));
    }
}
