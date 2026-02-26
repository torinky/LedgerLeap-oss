<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\TextareaType;
use App\Models\ColumnTypes\TextType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TextType / TextareaType のテスト
 */
#[CoversClass(TextType::class)]
#[CoversClass(TextareaType::class)]
class TextTypesTest extends TestCase
{
    // ================================================================
    // TextType
    // ================================================================

    #[Test]
    public function text_type_get_name_returns_text(): void
    {
        $type = new TextType;
        $this->assertEquals('text', $type->getName());
    }

    #[Test]
    public function text_type_get_label_returns_non_empty(): void
    {
        $type = new TextType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function text_type_has_options_returns_false(): void
    {
        $type = new TextType;
        $this->assertFalse($type->hasOptions());
    }

    #[Test]
    public function text_type_should_convert_to_json_returns_false(): void
    {
        $type = new TextType;
        $this->assertFalse($type->shouldConvertToJson());
    }

    #[Test]
    public function text_type_is_hidden_returns_false(): void
    {
        $type = new TextType;
        $this->assertFalse($type->isHidden());
    }

    #[Test]
    public function text_type_get_validation_rules_returns_string_rule(): void
    {
        $type = new TextType;
        $this->assertEquals(['string'], $type->getValidationRules());
    }

    #[Test]
    public function text_type_convert_to_text_returns_string(): void
    {
        $type = new TextType;
        $this->assertEquals('hello', $type->convertToText('hello'));
        $this->assertEquals('42', $type->convertToText(42));
    }

    #[Test]
    public function text_type_restore_from_string_returns_value(): void
    {
        $type = new TextType;
        $this->assertEquals('hello', $type->restoreFromString('hello'));
        $this->assertNull($type->restoreFromString(null));
    }

    // ================================================================
    // TextareaType
    // ================================================================

    #[Test]
    public function textarea_type_get_name_returns_textarea(): void
    {
        $type = new TextareaType;
        $this->assertEquals('textarea', $type->getName());
    }

    #[Test]
    public function textarea_type_get_label_returns_non_empty(): void
    {
        $type = new TextareaType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function textarea_type_has_options_returns_false(): void
    {
        $type = new TextareaType;
        $this->assertFalse($type->hasOptions());
    }

    #[Test]
    public function textarea_type_should_convert_to_json_returns_false(): void
    {
        $type = new TextareaType;
        $this->assertFalse($type->shouldConvertToJson());
    }

    #[Test]
    public function textarea_type_is_hidden_returns_false(): void
    {
        $type = new TextareaType;
        $this->assertFalse($type->isHidden());
    }

    #[Test]
    public function textarea_type_get_validation_rules_returns_string_rule(): void
    {
        $type = new TextareaType;
        $this->assertEquals(['string'], $type->getValidationRules());
    }

    #[Test]
    public function textarea_type_convert_to_text_returns_string(): void
    {
        $type = new TextareaType;
        $this->assertEquals('some text', $type->convertToText('some text'));
    }

    #[Test]
    public function textarea_type_restore_from_string_returns_value(): void
    {
        $type = new TextareaType;
        $this->assertEquals('some text', $type->restoreFromString('some text'));
        $this->assertNull($type->restoreFromString(null));
    }
}
