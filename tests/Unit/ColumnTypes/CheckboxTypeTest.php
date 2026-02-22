<?php

namespace Tests\Unit\ColumnTypes;

use App\Models\ColumnTypes\CheckboxType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CheckboxType のテスト
 *
 * restoreFromString() の各パス（空、JSON文字列、配列、スカラー）、
 * convertToText()、getValidationRules() を検証する。
 */
#[CoversClass(CheckboxType::class)]
class CheckboxTypeTest extends TestCase
{
    // ================================================================
    // 基本プロパティ
    // ================================================================

    #[Test]
    public function get_name_returns_chk(): void
    {
        $type = new CheckboxType;
        $this->assertEquals('chk', $type->getName());
    }

    #[Test]
    public function get_label_returns_non_empty(): void
    {
        $type = new CheckboxType;
        $this->assertNotEmpty($type->getLabel());
    }

    #[Test]
    public function has_options_returns_true(): void
    {
        $type = new CheckboxType;
        $this->assertTrue($type->hasOptions());
    }

    #[Test]
    public function should_convert_to_json_returns_true(): void
    {
        $type = new CheckboxType;
        $this->assertTrue($type->shouldConvertToJson());
    }

    #[Test]
    public function is_hidden_returns_false(): void
    {
        $type = new CheckboxType;
        $this->assertFalse($type->isHidden());
    }

    #[Test]
    public function get_validation_rules_returns_array(): void
    {
        $type = new CheckboxType;
        $this->assertEquals(['array'], $type->getValidationRules());
    }

    // ================================================================
    // convertToText
    // ================================================================

    #[Test]
    public function convert_to_text_returns_array_when_array_given(): void
    {
        $type = new CheckboxType;
        $input = ['option1' => true, 'option2' => false];
        // 二重エンコード厳禁：配列はそのまま返す
        $result = $type->convertToText($input);
        $this->assertIsArray($result);
        $this->assertEquals($input, $result);
    }

    #[Test]
    public function convert_to_text_returns_string_when_scalar_given(): void
    {
        $type = new CheckboxType;
        $result = $type->convertToText('some_value');
        $this->assertIsString($result);
        $this->assertEquals('some_value', $result);
    }

    // ================================================================
    // restoreFromString
    // ================================================================

    #[Test]
    public function restore_from_string_returns_empty_array_when_empty(): void
    {
        $type = new CheckboxType;
        $this->assertEquals([], $type->restoreFromString(''));
        $this->assertEquals([], $type->restoreFromString(null));
        $this->assertEquals([], $type->restoreFromString([]));
    }

    #[Test]
    public function restore_from_string_parses_json_array_string(): void
    {
        $type = new CheckboxType;
        $json = '["option1", "option2"]';
        $result = $type->restoreFromString($json);
        $this->assertEquals(['option1', 'option2'], $result);
    }

    #[Test]
    public function restore_from_string_parses_json_object_string(): void
    {
        $type = new CheckboxType;
        $json = '{"key1": true, "key2": false}';
        $result = $type->restoreFromString($json);
        $this->assertEquals(['key1' => true, 'key2' => false], $result);
    }

    #[Test]
    public function restore_from_string_returns_array_when_array_given(): void
    {
        $type = new CheckboxType;
        $input = ['a', 'b'];
        $result = $type->restoreFromString($input);
        $this->assertEquals($input, $result);
    }

    #[Test]
    public function restore_from_string_wraps_plain_string_in_array(): void
    {
        $type = new CheckboxType;
        $result = $type->restoreFromString('single_value');
        $this->assertEquals(['single_value'], $result);
    }

    #[Test]
    public function restore_from_string_handles_invalid_json_gracefully(): void
    {
        $type = new CheckboxType;
        // [ で始まる不正なJSON → フォールバックで配列にラップ
        $result = $type->restoreFromString('[invalid json');
        $this->assertIsArray($result);
    }
}
