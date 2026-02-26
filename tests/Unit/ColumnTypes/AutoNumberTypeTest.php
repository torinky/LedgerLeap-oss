<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\AutoNumberType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AutoNumberType のテスト
 *
 * コンストラクタオプション、getValidationRules() の prefix/digits 組み合わせ、
 * convertToText()、restoreFromString() を検証する。
 */
#[CoversClass(AutoNumberType::class)]
class AutoNumberTypeTest extends TestCase
{
    // ================================================================
    // 基本プロパティ
    // ================================================================

    #[Test]
    public function get_name_returns_auto_number(): void
    {
        $type = new AutoNumberType;
        $this->assertEquals('auto_number', $type->getName());
    }

    #[Test]
    public function get_label_returns_non_empty(): void
    {
        $type = new AutoNumberType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function has_options_returns_true(): void
    {
        $type = new AutoNumberType;
        $this->assertTrue($type->hasOptions());
    }

    #[Test]
    public function should_convert_to_json_returns_false(): void
    {
        $type = new AutoNumberType;
        $this->assertFalse($type->shouldConvertToJson());
    }

    #[Test]
    public function is_hidden_returns_false(): void
    {
        $type = new AutoNumberType;
        $this->assertFalse($type->isHidden());
    }

    // ================================================================
    // コンストラクタ
    // ================================================================

    #[Test]
    public function constructor_sets_options(): void
    {
        $type = new AutoNumberType([
            'prefix' => 'INV-',
            'digits' => 5,
            'revision' => 'A',
        ]);

        $this->assertEquals('INV-', $type->prefix);
        $this->assertEquals(5, $type->digits);
        $this->assertEquals('A', $type->revision);
    }

    #[Test]
    public function constructor_defaults_to_null(): void
    {
        $type = new AutoNumberType;

        $this->assertNull($type->prefix);
        $this->assertNull($type->digits);
        $this->assertNull($type->revision);
    }

    // ================================================================
    // getValidationRules
    // ================================================================

    #[Test]
    public function get_validation_rules_returns_min_zero_when_no_options(): void
    {
        $type = new AutoNumberType;
        $rules = $type->getValidationRules();
        $this->assertContains('string', $rules);
        $this->assertContains('min:0', $rules);
    }

    #[Test]
    public function get_validation_rules_calculates_min_from_prefix_and_digits(): void
    {
        // prefix="INV-"(4文字) + digits=5 → min:9
        $type = new AutoNumberType(['prefix' => 'INV-', 'digits' => 5]);
        $rules = $type->getValidationRules();
        $this->assertContains('min:9', $rules);
    }

    #[Test]
    public function get_validation_rules_uses_prefix_length_only_when_no_digits(): void
    {
        // prefix="AB"(2文字) + digits=null(0) → min:2
        $type = new AutoNumberType(['prefix' => 'AB']);
        $rules = $type->getValidationRules();
        $this->assertContains('min:2', $rules);
    }

    #[Test]
    public function get_validation_rules_uses_digits_only_when_no_prefix(): void
    {
        // prefix=null(0) + digits=4 → min:4
        $type = new AutoNumberType(['digits' => 4]);
        $rules = $type->getValidationRules();
        $this->assertContains('min:4', $rules);
    }

    // ================================================================
    // convertToText / restoreFromString
    // ================================================================

    #[Test]
    public function convert_to_text_returns_string(): void
    {
        $type = new AutoNumberType;
        $this->assertEquals('INV-00001', $type->convertToText('INV-00001'));
        $this->assertEquals('123', $type->convertToText(123));
    }

    #[Test]
    public function restore_from_string_returns_value_as_is(): void
    {
        $type = new AutoNumberType;
        $this->assertEquals('INV-00001', $type->restoreFromString('INV-00001'));
        $this->assertNull($type->restoreFromString(null));
    }
}
